<?php

declare(strict_types=1);

namespace Semitexa\Api\OpenApi\Route;

use ReflectionClass;
use Semitexa\Api\Attribute\CollectionFilterable;
use Semitexa\Api\Attribute\CollectionSortable;
use Semitexa\Api\Attribute\ProducesResourceCollection;
use Semitexa\Api\Attribute\ProducesResourceObject;
use Semitexa\Core\Resource\Pagination\CollectionPageRequest;
use Semitexa\Api\OpenApi\Schema\IncludeTokenCollector;
use Semitexa\Api\OpenApi\Schema\ResourceSchemaGenerator;
use Semitexa\Core\Attribute\AbstractPayloadRoute;
use Semitexa\Core\Attribute\AsService;
use Semitexa\Core\Attribute\InjectAsReadonly;
use Semitexa\Core\Discovery\ClassDiscovery;
use Semitexa\Core\Resource\Metadata\ResourceMetadataRegistry;
use Semitexa\Core\Resource\RenderProfile;
use Semitexa\Core\Resource\SupportsResourceIncludes;

/**
 * Phase 3b: produce OpenAPI 3.1 path operations for routes whose payload
 * declares `renderProfile: RenderProfile::Json` AND whose responseWith
 * carries `#[ProducesResourceObject(SomeResource::class)]`.
 *
 * Pure: zero IO at runtime, no DB, no ORM, no HTTP, no rendering. Reads only
 * already-discovered route metadata + reflection on the payload + response
 * classes.
 */
#[AsService]
final class ResourceRouteSchemaGenerator
{
    #[InjectAsReadonly]
    protected ClassDiscovery $classDiscovery;

    #[InjectAsReadonly]
    protected ResourceMetadataRegistry $registry;

    #[InjectAsReadonly]
    protected ResourceSchemaGenerator $schemaGenerator;

    #[InjectAsReadonly]
    protected IncludeTokenCollector $includeTokens;

    public static function forTesting(
        ClassDiscovery $classDiscovery,
        ResourceMetadataRegistry $registry,
        ResourceSchemaGenerator $schemaGenerator,
        IncludeTokenCollector $includeTokens,
    ): self {
        $g = new self();
        $g->classDiscovery   = $classDiscovery;
        $g->registry         = $registry;
        $g->schemaGenerator  = $schemaGenerator;
        $g->includeTokens    = $includeTokens;
        return $g;
    }

    /**
     * Build the `paths` map for OpenAPI's top-level document.
     *
     * @return array<string, array<string, mixed>> keyed by URL path
     */
    public function generatePaths(): array
    {
        $paths = [];

        foreach ($this->discoverPayloadClasses() as $payloadClass) {
            $route = $this->describeRoute($payloadClass);
            if ($route === null) {
                continue;
            }

            $existing = $paths[$route['path']] ?? [];
            foreach ($route['methods'] as $method) {
                $methodKey = strtolower($method);
                $methodOp  = $route['operation'];
                $methodOp['operationId'] = $this->operationId($payloadClass, $method);
                $bodies    = $route['requestBodiesByMethod'] ?? [];
                if (isset($bodies[$methodKey])) {
                    $methodOp['requestBody'] = $bodies[$methodKey];
                }
                $existing[$methodKey] = $methodOp;
            }
            $paths[$route['path']] = $existing;
        }

        ksort($paths);
        return $paths;
    }

    /**
     * @param class-string $payloadClass
     * @return array{path: string, methods: list<string>, operation: array<string, mixed>}|null
     */
    public function describeRoute(string $payloadClass): ?array
    {
        $payloadRef = new ReflectionClass($payloadClass);
        $payloadAttrs = $payloadRef->getAttributes(AbstractPayloadRoute::class, \ReflectionAttribute::IS_INSTANCEOF);
        if ($payloadAttrs === []) {
            return null;
        }
        /** @var AbstractPayloadRoute $asPayload */
        $asPayload = $payloadAttrs[0]->newInstance();

        if (!$this->isJsonProfile($asPayload->renderProfile)) {
            return null;
        }

        $responseClass = $asPayload->responseWith;
        if (!is_string($responseClass) || $responseClass === '' || !class_exists($responseClass)) {
            return null;
        }

        $resourceClass = $this->resolveResourceClass($responseClass);
        if ($resourceClass === null) {
            return null;
        }
        if (!$this->registry->has($resourceClass)) {
            return null;
        }
        $isCollection = $this->responseProducesCollection($responseClass);

        $path    = $asPayload->path ?? '';
        $methods = $asPayload->methods ?? ['GET'];
        if ($path === '' || $methods === []) {
            return null;
        }

        $resourceMeta = $this->registry->require($resourceClass);
        $componentName = $this->schemaGenerator->componentName($resourceMeta);

        $parameters = $this->extractPathParameters($path);

        // Phase 3c: emit `include=` parameter only when the payload's runtime
        // contract actually accepts it (i.e. it implements
        // `SupportsResourceIncludes`). Routes that don't honour `?include=`
        // at runtime must not advertise it in OpenAPI.
        if ($payloadRef->implementsInterface(SupportsResourceIncludes::class)) {
            $tokens = $this->includeTokens->collect($resourceMeta);
            if ($tokens !== []) {
                $parameters[] = $this->buildIncludeParameter($tokens);
            }
        }

        // Phase 5f: routes that declare `RenderProfile::GraphQL` accept a
        // bounded GraphQL query via `?query=` (Phase 5c GET transport, also
        // a fallback on POST when no body is sent). Document the parameter
        // alongside the existing `include` parameter on every method served
        // by this route — both GET and POST consult it. The `requestBody`
        // (Phase 5e) covers the POST-specific body transport separately.
        if ($this->declaresGraphQL($asPayload->renderProfile)) {
            $parameters[] = $this->buildGraphqlQueryParameter();
        }

        // Phase 6i: collection routes accept the deterministic
        // `?page=` / `?perPage=` pagination parameters. The handler
        // slices the underlying collection before resolver expansion
        // and stamps a `meta.pagination` block in the response
        // envelope. Only collection-shaped routes advertise the
        // parameters; singular routes do not.
        if ($isCollection) {
            $parameters[] = $this->buildPageParameter();
            $parameters[] = $this->buildPerPageParameter();

            // Phase 6j: a collection response that carries
            // `#[CollectionSortable]` advertises the bounded `?sort=`
            // parameter. Routes without the attribute (or with an
            // empty allowlist) do not advertise it; the runtime
            // parser then rejects every non-empty value.
            $sortFields = $this->sortAllowlistFor($responseClass);
            if ($sortFields !== []) {
                $parameters[] = $this->buildSortParameter($sortFields);
            }

            // Phase 6k: a collection response that carries
            // `#[CollectionFilterable]` advertises the bounded
            // `?filter=` parameter. Same allowlist contract as sort;
            // routes without the attribute (or with an empty map)
            // do not advertise it.
            $filterFields = $this->filterAllowlistFor($responseClass);
            if ($filterFields !== []) {
                $parameters[] = $this->buildFilterParameter($filterFields);
            }

            // Phase 6l: collection routes also advertise the optional
            // `?cursor=` parameter. The cursor is opaque, mutually
            // exclusive with `?page=`, and tied to the current sort
            // + filter context. Singular routes do not advertise it.
            $parameters[] = $this->buildCursorParameter();
        }

        $paginationSchema = [
            'type'       => 'object',
            'required'   => ['page', 'perPage', 'total', 'pageCount', 'hasNext', 'hasPrevious'],
            'properties' => [
                'page'        => ['type' => 'integer', 'minimum' => 1],
                'perPage'     => ['type' => 'integer', 'minimum' => 1],
                'total'       => ['type' => 'integer', 'minimum' => 0],
                'pageCount'   => ['type' => 'integer', 'minimum' => 0],
                'hasNext'     => ['type' => 'boolean'],
                'hasPrevious' => ['type' => 'boolean'],
            ],
            'additionalProperties' => false,
        ];

        $jsonSchema = $isCollection
            ? [
                'type'       => 'object',
                'required'   => ['data', 'meta'],
                'properties' => [
                    'data' => [
                        'type'  => 'array',
                        'items' => ['$ref' => '#/components/schemas/' . $componentName],
                    ],
                    'meta' => [
                        'type'       => 'object',
                        'required'   => ['pagination'],
                        'properties' => [
                            'pagination' => $paginationSchema,
                        ],
                        'additionalProperties' => false,
                    ],
                ],
            ]
            : [
                'type'       => 'object',
                'required'   => ['data'],
                'properties' => [
                    'data' => ['$ref' => '#/components/schemas/' . $componentName],
                ],
            ];
        $content = ['application/json' => ['schema' => $jsonSchema]];

        // Phase 3e: when the route declares JSON-LD in addition to JSON,
        // advertise both content types. JSON-LD body shape is "the same
        // Resource fields plus @context/@id/@type per the framework's
        // JSON-LD vocabulary"; full JSON-LD schema generation is deferred
        // to a later phase, so the application/ld+json schema is a generic
        // object with a description rather than a fully typed component.
        if ($this->declaresJsonLd($asPayload->renderProfile)) {
            $content['application/ld+json'] = [
                'schema' => [
                    'type'        => 'object',
                    'description' => 'JSON-LD encoding of ' . $componentName
                        . '. Same Resource fields plus @context, @id, @type per the framework JSON-LD vocabulary. Detailed JSON-LD schema generation is a future phase.',
                ],
            ];
        }

        // Phase 5b: when the route declares the GraphQL profile, advertise
        // `application/graphql-response+json` in the response content map.
        // The schema is intentionally conservative — `{data: {object}}` —
        // because Phase 5a is a render-profile integration, not a GraphQL
        // schema generator. Detailed GraphQL schema generation, field
        // selection, and introspection remain out of scope.
        if ($this->declaresGraphQL($asPayload->renderProfile)) {
            $content['application/graphql-response+json'] = [
                'schema' => [
                    'type'        => 'object',
                    'required'    => ['data'],
                    'properties'  => [
                        'data' => [
                            'type'                 => 'object',
                            'additionalProperties' => true,
                        ],
                    ],
                    'description' => 'GraphQL-compatible response envelope for '
                        . $componentName
                        . '. Detailed GraphQL schema generation, field selection, and introspection are out of scope for this phase.',
                ],
            ];
        }

        $operation = [
            'operationId' => $this->operationId($payloadClass, $methods[0] ?? 'GET'),
            'parameters'  => $parameters,
            'responses'   => [
                '200' => [
                    'description' => 'Successful response.',
                    'content'     => $content,
                ],
            ],
        ];

        if ($operation['parameters'] === []) {
            unset($operation['parameters']);
        }

        // Phase 5e: when this route declares the GraphQL profile, its POST
        // operation accepts a GraphQL request body (Phase 5d transport). The
        // requestBody is method-scoped — GET keeps the bare base operation;
        // generatePaths() merges the per-method body into the right slot.
        $requestBodiesByMethod = [];
        if ($this->declaresGraphQL($asPayload->renderProfile)) {
            $requestBodiesByMethod['post'] = $this->buildGraphqlRequestBody($componentName);
        }

        return [
            'path'                  => $path,
            'methods'               => $methods,
            'operation'             => $operation,
            'requestBodiesByMethod' => $requestBodiesByMethod,
        ];
    }

    /** @return list<class-string> */
    public function discoverPayloadClasses(): array
    {
        $classes = $this->classDiscovery->findClassesWithAttributeInstanceof(AbstractPayloadRoute::class);
        sort($classes);
        /** @var list<class-string> $classes */
        return $classes;
    }

    /**
     * Phase 6h: a response class may declare either
     * `#[ProducesResourceObject]` (singular) or
     * `#[ProducesResourceCollection]` (collection); the two are
     * mutually exclusive but indicate the same underlying resource
     * class. The collection branch wins when both are present so the
     * generator never silently emits a singular schema for a
     * collection response.
     *
     * @param class-string $responseClass
     * @return class-string|null
     */
    private function resolveResourceClass(string $responseClass): ?string
    {
        $ref = new ReflectionClass($responseClass);

        $collectionAttrs = $ref->getAttributes(ProducesResourceCollection::class);
        if ($collectionAttrs !== []) {
            /** @var ProducesResourceCollection $produces */
            $produces = $collectionAttrs[0]->newInstance();
            if (!class_exists($produces->resourceClass)) {
                return null;
            }
            /** @var class-string $resourceClass */
            $resourceClass = $produces->resourceClass;
            return $resourceClass;
        }

        $attrs = $ref->getAttributes(ProducesResourceObject::class);
        if ($attrs === []) {
            return null;
        }
        /** @var ProducesResourceObject $produces */
        $produces = $attrs[0]->newInstance();
        if (!class_exists($produces->resourceClass)) {
            return null;
        }
        /** @var class-string $resourceClass */
        $resourceClass = $produces->resourceClass;
        return $resourceClass;
    }

    /**
     * @param class-string $responseClass
     */
    private function responseProducesCollection(string $responseClass): bool
    {
        $ref = new ReflectionClass($responseClass);
        return $ref->getAttributes(ProducesResourceCollection::class) !== [];
    }

    /**
     * Phase 6j: read the route's sort allowlist from the response
     * class's `#[CollectionSortable]` attribute. Returns an empty
     * list when the attribute is absent, which suppresses the
     * `?sort=` parameter on the operation entirely.
     *
     * @param class-string $responseClass
     * @return list<string>
     */
    private function sortAllowlistFor(string $responseClass): array
    {
        $ref   = new ReflectionClass($responseClass);
        $attrs = $ref->getAttributes(CollectionSortable::class);
        if ($attrs === []) {
            return [];
        }
        /** @var CollectionSortable $sortable */
        $sortable = $attrs[0]->newInstance();
        return array_values($sortable->fields);
    }

    /**
     * Phase 6k: read the route's filter allowlist from the response
     * class's `#[CollectionFilterable]` attribute. Returns an empty
     * map when the attribute is absent, which suppresses the
     * `?filter=` parameter on the operation entirely.
     *
     * @param class-string $responseClass
     * @return array<string, list<string>>
     */
    private function filterAllowlistFor(string $responseClass): array
    {
        $ref   = new ReflectionClass($responseClass);
        $attrs = $ref->getAttributes(CollectionFilterable::class);
        if ($attrs === []) {
            return [];
        }
        /** @var CollectionFilterable $filterable */
        $filterable = $attrs[0]->newInstance();
        return $filterable->fields;
    }

    /**
     * Build an OpenAPI 3.1 `include` query parameter from a sorted list of
     * accepted tokens. v1 uses the simple `enum` representation: each token
     * lists what the *runtime* IncludeValidator accepts; the description
     * notes that values may be comma-separated client-side.
     *
     * @param list<string> $tokens
     * @return array<string, mixed>
     */
    private function buildIncludeParameter(array $tokens): array
    {
        return [
            'name'        => 'include',
            'in'          => 'query',
            'required'    => false,
            'description' => 'Comma-separated list of relations to include in the response. Each value must be one of the listed enum tokens; tokens may be combined (e.g. "addresses,profile"). Unknown or non-expandable tokens return HTTP 400.',
            'style'       => 'form',
            'explode'     => false,
            'schema'      => [
                'type'  => 'array',
                'items' => [
                    'type' => 'string',
                    'enum' => $tokens,
                ],
            ],
        ];
    }

    /**
     * Phase 6i: `?page=` query parameter for collection routes.
     * Optional integer; defaults to 1; values < 1 raise HTTP 400.
     *
     * @return array<string, mixed>
     */
    private function buildPageParameter(): array
    {
        return [
            'name'        => 'page',
            'in'          => 'query',
            'required'    => false,
            'description' => sprintf(
                'One-based page number for collection responses. Defaults to %d. '
                . 'Values less than 1 return HTTP 400. '
                . 'Pages beyond the last return an empty data array with valid pagination metadata.',
                CollectionPageRequest::DEFAULT_PAGE,
            ),
            'schema'      => [
                'type'    => 'integer',
                'minimum' => 1,
                'default' => CollectionPageRequest::DEFAULT_PAGE,
            ],
        ];
    }

    /**
     * Phase 6i: `?perPage=` query parameter for collection routes.
     * Optional integer in `[1, MAX_PER_PAGE]`; defaults to 10;
     * values out of range raise HTTP 400.
     *
     * @return array<string, mixed>
     */
    private function buildPerPageParameter(): array
    {
        return [
            'name'        => 'perPage',
            'in'          => 'query',
            'required'    => false,
            'description' => sprintf(
                'Page size for collection responses. Defaults to %d. '
                . 'Range: 1..%d. Values outside the range return HTTP 400.',
                CollectionPageRequest::DEFAULT_PER_PAGE,
                CollectionPageRequest::MAX_PER_PAGE,
            ),
            'schema'      => [
                'type'    => 'integer',
                'minimum' => 1,
                'maximum' => CollectionPageRequest::MAX_PER_PAGE,
                'default' => CollectionPageRequest::DEFAULT_PER_PAGE,
            ],
        ];
    }

    /**
     * Phase 6l: `?cursor=` query parameter for collection routes.
     *
     * The cursor is an opaque, base64url-encoded token returned by a
     * previous response. It is mutually exclusive with `?page=` and
     * tied to the current `?sort=` + `?filter=` context: clients
     * must replay the same sort and filter parameters that produced
     * the cursor, or the request fails HTTP 400. The schema is plain
     * string — the token's internal shape is implementation-defined
     * and may change between framework versions.
     *
     * @return array<string, mixed>
     */
    private function buildCursorParameter(): array
    {
        return [
            'name'        => 'cursor',
            'in'          => 'query',
            'required'    => false,
            'description' => 'Opaque cursor token returned by a previous response. '
                . 'Mutually exclusive with ?page= (HTTP 400 if both present). '
                . 'Tied to the current ?sort= and ?filter= context — replay the cursor with the '
                . 'same sort and filter parameters that produced it. '
                . 'Cursor mode response carries meta.pagination.mode = "cursor" with '
                . 'perPage, total, hasNext, nextCursor, and the echoed cursor. '
                . 'Cursor windowing applies after filter and sort, before resolver expansion. '
                . 'A malformed, expired-version, or context-mismatched cursor returns HTTP 400.',
            'schema'      => [
                'type' => 'string',
            ],
        ];
    }

    /**
     * Phase 6k: `?filter=` query parameter for collection routes that
     * declare a non-empty `#[CollectionFilterable]` allowlist.
     *
     * Wire syntax: a single string with semicolon-separated terms,
     * each term shaped `field:operator:value`. Multi-term filters
     * use AND semantics. Operators are bounded per field by the
     * route's allowlist; out-of-allowlist combinations return
     * HTTP 400.
     *
     * Schema is plain string with a rich description; the comma /
     * semicolon / per-field-operator combinatorics cannot be
     * enumerated finitely.
     *
     * @param array<string, list<string>> $allowedFilters
     * @return array<string, mixed>
     */
    private function buildFilterParameter(array $allowedFilters): array
    {
        $perField = [];
        foreach ($allowedFilters as $field => $operators) {
            $perField[] = sprintf('%s [%s]', $field, implode('|', $operators));
        }
        // Pick a deterministic example for clients that surface them.
        $exampleField = array_key_first($allowedFilters) ?? 'field';
        $exampleOp    = $allowedFilters[$exampleField][0] ?? 'eq';

        return [
            'name'        => 'filter',
            'in'          => 'query',
            'required'    => false,
            'description' => sprintf(
                'Bounded collection filter expression. Single parameter, '
                . 'semicolon-separated terms with AND semantics. '
                . 'Each term is "field:operator:value". '
                . '"in" values are comma-separated. '
                . '"contains" is a case-insensitive substring match on string fields. '
                . 'Allowed (Phase 6k allowlist): %s. '
                . 'Examples: "%s:%s:value", '
                . '"id:in:1,2,3", "name:contains:acme", '
                . '"id:in:1,2;name:contains:acme". '
                . 'Unknown / nested / duplicate fields, unsupported operators, '
                . 'or empty values return HTTP 400. '
                . 'Filtering happens before sorting and pagination; '
                . 'meta.pagination.total reflects the filtered total.',
                implode(', ', $perField),
                $exampleField,
                $exampleOp,
            ),
            'schema'      => [
                'type'    => 'string',
                'example' => sprintf('%s:%s:value', $exampleField, $exampleOp),
            ],
        ];
    }

    /**
     * Phase 6j: `?sort=` query parameter for collection routes that
     * declare a non-empty `#[CollectionSortable]` allowlist.
     *
     * The wire syntax supports both single-field and comma-separated
     * forms (e.g. `name`, `-name`, `name,id`, `-name,id`). The schema
     * is a plain string with a rich description listing the allowed
     * fields; an `enum` would not capture the comma-combined cases
     * deterministically.
     *
     * @param list<string> $allowedFields
     * @return array<string, mixed>
     */
    private function buildSortParameter(array $allowedFields): array
    {
        $allowed = implode(', ', $allowedFields);
        $singleFieldEnum = [];
        foreach ($allowedFields as $field) {
            $singleFieldEnum[] = $field;
            $singleFieldEnum[] = '-' . $field;
        }
        sort($singleFieldEnum);

        return [
            'name'        => 'sort',
            'in'          => 'query',
            'required'    => false,
            'description' => sprintf(
                'Comma-separated sort fields applied before pagination. '
                . 'Prefix a field with "-" for descending. '
                . 'Allowed fields (Phase 6j allowlist): %s. '
                . 'Single-field examples: %s. '
                . 'Multi-field example: "name,id" (sorts by name, ties broken by id). '
                . 'Unknown / nested / duplicate / "--" fields return HTTP 400. '
                . 'Empty value preserves the route\'s default catalog order.',
                $allowed,
                implode(', ', $singleFieldEnum),
            ),
            'schema'      => [
                'type' => 'string',
                // Examples for clients that surface them (Swagger UI,
                // ReDoc). Examples-not-enum: comma-separated forms
                // are valid but cannot be enumerated finitely.
                'example' => $allowedFields[0],
            ],
        ];
    }

    /**
     * Phase 5f: GraphQL `?query=` query parameter for routes that declare
     * `RenderProfile::GraphQL`. Documents the GET transport (Phase 5c)
     * and the POST fallback (Phase 5d, when no body is sent).
     *
     * The schema is a plain `string` — the bounded Phase 5c parser
     * handles validation; OpenAPI tooling does not need to know the
     * GraphQL grammar to communicate "send a string here".
     *
     * @return array<string, mixed>
     */
    private function buildGraphqlQueryParameter(): array
    {
        return [
            'name'        => 'query',
            'in'          => 'query',
            'required'    => false,
            'description' => 'GraphQL query string in the bounded Semitexa GraphQL subset. '
                . 'Mutations, subscriptions, fragments, variables, directives, aliases, '
                . 'and field arguments are rejected with HTTP 400. '
                . 'For POST requests, request body query takes precedence over ?query=. '
                . 'The ?query= parameter takes precedence over ?include=.',
            'schema'      => [
                'type' => 'string',
            ],
        ];
    }

    /**
     * Phase 5e: GraphQL POST request body. Two content types, both honest
     * about the bounded Phase 5c/5d runtime contract:
     *
     *   - `application/json` with `{query: string, variables?, operationName?}`.
     *     `variables` is documented as unsupported (must be omitted, null,
     *     or empty). `operationName` is accepted but ignored.
     *   - `application/graphql` raw GraphQL query string.
     *
     * `additionalProperties: true` reflects the runtime: unknown harmless
     * top-level fields in the JSON body are tolerated; only `variables`
     * (when non-empty) is rejected.
     *
     * @return array<string, mixed>
     */
    private function buildGraphqlRequestBody(string $componentName): array
    {
        return [
            'description' => 'GraphQL request body for ' . $componentName
                . '. Body query takes precedence over the `?query=` query parameter '
                . '(Phase 5c) and the `?include=` parameter. The JSON envelope '
                . 'follows the GraphQL over HTTP draft; raw `application/graphql` '
                . 'bodies carry the query string directly.',
            'required'    => false,
            'content'     => [
                'application/json' => [
                    'schema' => [
                        'type'                 => 'object',
                        'required'             => ['query'],
                        'additionalProperties' => true,
                        'properties'           => [
                            'query' => [
                                'type'        => 'string',
                                'description' => 'GraphQL query string in the bounded Semitexa GraphQL subset (Phase 5c parser). Mutations, subscriptions, fragments, variables, directives, aliases, and field arguments are rejected with HTTP 400.',
                            ],
                            'variables' => [
                                'description' => 'Variables are not supported in this phase. Must be omitted, null, or empty. Non-empty values return HTTP 400.',
                            ],
                            'operationName' => [
                                'type'        => 'string',
                                'description' => 'Accepted but ignored. The bounded parser already handles named-query syntax and selects a single root field per request.',
                            ],
                        ],
                    ],
                ],
                'application/graphql' => [
                    'schema' => [
                        'type'        => 'string',
                        'description' => 'Raw GraphQL query string in the bounded Semitexa GraphQL subset.',
                    ],
                ],
            ],
        ];
    }

    /** @return list<array<string, mixed>> */
    private function extractPathParameters(string $path): array
    {
        if (!preg_match_all('/\{([a-zA-Z_][a-zA-Z0-9_]*)\}/', $path, $matches)) {
            return [];
        }

        $params = [];
        foreach ($matches[1] as $name) {
            $params[] = [
                'name'     => $name,
                'in'       => 'path',
                'required' => true,
                'schema'   => ['type' => 'string'],
            ];
        }
        return $params;
    }

    private function declaresGraphQL(mixed $renderProfile): bool
    {
        if ($renderProfile === null) {
            return false;
        }
        if ($renderProfile instanceof RenderProfile) {
            return $renderProfile === RenderProfile::GraphQL;
        }
        if (is_array($renderProfile)) {
            foreach ($renderProfile as $entry) {
                if ($entry instanceof RenderProfile && $entry === RenderProfile::GraphQL) {
                    return true;
                }
            }
        }
        return false;
    }

    private function declaresJsonLd(mixed $renderProfile): bool
    {
        if ($renderProfile === null) {
            return false;
        }
        if ($renderProfile instanceof RenderProfile) {
            return $renderProfile === RenderProfile::JsonLd;
        }
        if (is_array($renderProfile)) {
            foreach ($renderProfile as $entry) {
                if ($entry instanceof RenderProfile && $entry === RenderProfile::JsonLd) {
                    return true;
                }
            }
        }
        return false;
    }

    private function isJsonProfile(mixed $renderProfile): bool
    {
        if ($renderProfile === null) {
            return false;
        }
        if ($renderProfile instanceof RenderProfile) {
            return $renderProfile === RenderProfile::Json;
        }
        if (is_array($renderProfile)) {
            foreach ($renderProfile as $entry) {
                if ($entry instanceof RenderProfile && $entry === RenderProfile::Json) {
                    return true;
                }
            }
        }
        return false;
    }

    private function operationId(string $payloadClass, string $method): string
    {
        $parts = explode('\\', $payloadClass);
        $base  = $parts[count($parts) - 1];
        $id    = preg_replace('/Payload$/', '', $base) ?? $base;

        $prefix = ucfirst(strtolower($method));
        if (str_starts_with($id, $prefix)) {
            return $id;
        }

        return $prefix . $id;
    }
}
