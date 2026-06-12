<?php

declare(strict_types=1);

namespace Semitexa\Api\Tests\Unit\OpenApi;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Semitexa\Api\Discovery\CollectionContractBlockContributor;
use Semitexa\Api\OpenApi\Route\ResourceRouteSchemaGenerator;
use Semitexa\Api\OpenApi\Schema\IncludeTokenCollector;
use Semitexa\Api\OpenApi\Schema\ResourceSchemaGenerator;
use Semitexa\Core\Discovery\ClassDiscovery;
use Semitexa\Core\Http\DefaultRouteContractAssembler;
use Semitexa\Core\Resource\Metadata\ResourceMetadataExtractor;
use Semitexa\Core\Resource\Metadata\ResourceMetadataRegistry;
use Semitexa\Api\Tests\Fixtures\Customer\AddressResource as FixtureAddressResource;
use Semitexa\Api\Tests\Fixtures\Customer\CustomerResource as FixtureCustomerResource;
use Semitexa\Api\Tests\Fixtures\Customer\GetCustomerPayload;
use Semitexa\Api\Tests\Fixtures\Customer\ListCustomersPayload;
use Semitexa\Api\Tests\Fixtures\Customer\ProfilePreferencesResource as FixtureProfilePreferencesResource;
use Semitexa\Api\Tests\Fixtures\Customer\ProfileResource as FixtureProfileResource;

final class ResourceRouteSchemaGeneratorTest extends TestCase
{
    private function customerRegistry(): ResourceMetadataRegistry
    {
        $extractor = new ResourceMetadataExtractor();
        $registry  = ResourceMetadataRegistry::forTesting($extractor);
        $registry->register($extractor->extract(FixtureAddressResource::class));
        $registry->register($extractor->extract(FixtureProfilePreferencesResource::class));
        $registry->register($extractor->extract(FixtureProfileResource::class));
        $registry->register($extractor->extract(FixtureCustomerResource::class));
        return $registry;
    }

    private function buildGenerator(ResourceMetadataRegistry $registry, ClassDiscovery $discovery): ResourceRouteSchemaGenerator
    {
        $schemaGen     = ResourceSchemaGenerator::forTesting($registry);
        $includeTokens = IncludeTokenCollector::forTesting($registry);
        $assembler     = DefaultRouteContractAssembler::forTesting(
            $registry,
            [new CollectionContractBlockContributor()],
        );
        return ResourceRouteSchemaGenerator::forTesting($discovery, $registry, $schemaGen, $includeTokens, $assembler);
    }

    #[Test]
    public function describes_customer_route_with_path_and_id_param(): void
    {
        $registry = $this->customerRegistry();
        $generator = $this->buildGenerator(
            $registry,
            new InMemoryDiscovery([GetCustomerPayload::class]),
        );

        $route = $generator->describeRoute(GetCustomerPayload::class);
        self::assertNotNull($route);

        self::assertSame('/customers/{id}', $route['path']);
        // Phase 5d: route now serves both GET and POST so GraphQL clients
        // may send the query in the request body in addition to ?query=.
        self::assertSame(['GET', 'POST'], $route['methods']);

        $op = $route['operation'];
        self::assertSame('GetCustomer', $op['operationId']);
        // Phase 3b: id path parameter. Phase 3c: include query parameter.
        self::assertGreaterThanOrEqual(1, count($op['parameters']));
        self::assertSame('id', $op['parameters'][0]['name']);
        self::assertSame('path', $op['parameters'][0]['in']);
        self::assertTrue($op['parameters'][0]['required']);

        // 200 response references the CustomerResource component.
        $schemaRef = $op['responses']['200']['content']['application/json']['schema'];
        self::assertSame('object', $schemaRef['type']);
        self::assertSame(['data'], $schemaRef['required']);
        self::assertSame(
            ['$ref' => '#/components/schemas/CustomerResource'],
            $schemaRef['properties']['data'],
        );
    }

    #[Test]
    public function generate_paths_returns_a_map_keyed_by_url(): void
    {
        $registry = $this->customerRegistry();
        $generator = $this->buildGenerator(
            $registry,
            new InMemoryDiscovery([GetCustomerPayload::class]),
        );

        $paths = $generator->generatePaths();
        self::assertArrayHasKey('/customers/{id}', $paths);
        self::assertArrayHasKey('get', $paths['/customers/{id}']);
    }

    #[Test]
    public function emits_graphql_query_parameter_on_get_and_post_when_route_declares_graphql(): void
    {
        $registry  = $this->customerRegistry();
        $generator = $this->buildGenerator($registry, new InMemoryDiscovery([GetCustomerPayload::class]));
        $paths     = $generator->generatePaths();
        $route     = $paths['/customers/{id}'];

        foreach (['get', 'post'] as $methodKey) {
            self::assertArrayHasKey($methodKey, $route);
            $params = $route[$methodKey]['parameters'] ?? [];
            $names  = array_map(static fn ($p) => $p['name'] ?? null, $params);
            self::assertContains(
                'query',
                $names,
                sprintf('?query= parameter must appear on %s for GraphQL-capable routes.', strtoupper($methodKey)),
            );
        }
    }

    #[Test]
    public function graphql_query_parameter_has_correct_shape(): void
    {
        $registry  = $this->customerRegistry();
        $generator = $this->buildGenerator($registry, new InMemoryDiscovery([GetCustomerPayload::class]));
        $paths     = $generator->generatePaths();

        $params = $paths['/customers/{id}']['get']['parameters'];
        $queryParam = null;
        foreach ($params as $p) {
            if (($p['name'] ?? null) === 'query') {
                $queryParam = $p;
                break;
            }
        }

        self::assertNotNull($queryParam, '?query= parameter must be emitted.');
        self::assertSame('query', $queryParam['in']);
        self::assertFalse($queryParam['required']);
        self::assertSame(['type' => 'string'], $queryParam['schema']);

        $description = $queryParam['description'];
        self::assertStringContainsString('GraphQL query string', $description);
        self::assertStringContainsString('bounded Semitexa GraphQL subset', $description);
        self::assertStringContainsString('rejected with HTTP 400', $description);
        // Precedence rules called out in the description, matching Phase 5d runtime.
        self::assertStringContainsString('request body query takes precedence', $description);
        self::assertStringContainsString('?query= parameter takes precedence over ?include=', $description);
    }

    #[Test]
    public function does_not_emit_graphql_query_parameter_for_routes_without_graphql_profile(): void
    {
        $registry  = $this->customerRegistry();
        $generator = $this->buildGenerator(
            $registry,
            new InMemoryDiscovery([JsonOnlyPayloadFixture5b::class]),
        );

        $paths = $generator->generatePaths();
        $route = $paths['/phase5b/json-only/{id}'];

        foreach ($route as $methodKey => $op) {
            $names = array_map(static fn ($p) => $p['name'] ?? null, $op['parameters'] ?? []);
            self::assertNotContains(
                'query',
                $names,
                sprintf('%s must not advertise ?query= — route does not declare GraphQL profile.', strtoupper($methodKey)),
            );
        }
    }

    #[Test]
    public function existing_include_parameter_remains_alongside_graphql_query_parameter(): void
    {
        $registry  = $this->customerRegistry();
        $generator = $this->buildGenerator($registry, new InMemoryDiscovery([GetCustomerPayload::class]));
        $paths     = $generator->generatePaths();

        $params = $paths['/customers/{id}']['get']['parameters'];
        $names  = array_map(static fn ($p) => $p['name'] ?? null, $params);

        // Phase 3c include parameter must still be present alongside the
        // new Phase 5f query parameter — they are independent.
        self::assertContains('id', $names);
        self::assertContains('include', $names);
        self::assertContains('query', $names);
    }

    #[Test]
    public function emits_graphql_request_body_only_for_post_when_route_declares_graphql(): void
    {
        $registry  = $this->customerRegistry();
        $generator = $this->buildGenerator($registry, new InMemoryDiscovery([GetCustomerPayload::class]));

        $paths = $generator->generatePaths();
        $route = $paths['/customers/{id}'];

        // GET must NOT have a requestBody.
        self::assertArrayHasKey('get', $route);
        self::assertArrayNotHasKey('requestBody', $route['get']);

        // POST must have a requestBody.
        self::assertArrayHasKey('post', $route);
        self::assertArrayHasKey('requestBody', $route['post']);

        $body = $route['post']['requestBody'];
        self::assertFalse($body['required']);
        self::assertStringContainsString('GraphQL request body', $body['description']);
        self::assertStringContainsString('precedence', $body['description']);

        self::assertArrayHasKey('application/json', $body['content']);
        self::assertArrayHasKey('application/graphql', $body['content']);
    }

    #[Test]
    public function graphql_application_json_request_body_requires_query_field(): void
    {
        $registry  = $this->customerRegistry();
        $generator = $this->buildGenerator($registry, new InMemoryDiscovery([GetCustomerPayload::class]));
        $paths     = $generator->generatePaths();

        $jsonSchema = $paths['/customers/{id}']['post']['requestBody']
            ['content']['application/json']['schema'];

        self::assertSame('object', $jsonSchema['type']);
        self::assertSame(['query'], $jsonSchema['required']);
        self::assertTrue($jsonSchema['additionalProperties']);

        self::assertSame('string', $jsonSchema['properties']['query']['type']);
        self::assertStringContainsString('Phase 5c parser', $jsonSchema['properties']['query']['description']);

        // variables: documented as unsupported. No `type` (any-type), but
        // a clear description noting that non-empty values are rejected.
        self::assertArrayHasKey('variables', $jsonSchema['properties']);
        self::assertStringContainsString('not supported', $jsonSchema['properties']['variables']['description']);

        // operationName: accepted but ignored.
        self::assertArrayHasKey('operationName', $jsonSchema['properties']);
        self::assertSame('string', $jsonSchema['properties']['operationName']['type']);
        self::assertStringContainsString('ignored', $jsonSchema['properties']['operationName']['description']);
    }

    #[Test]
    public function graphql_application_graphql_request_body_is_a_string(): void
    {
        $registry  = $this->customerRegistry();
        $generator = $this->buildGenerator($registry, new InMemoryDiscovery([GetCustomerPayload::class]));
        $paths     = $generator->generatePaths();

        $rawSchema = $paths['/customers/{id}']['post']['requestBody']
            ['content']['application/graphql']['schema'];

        self::assertSame('string', $rawSchema['type']);
        self::assertStringContainsString('Raw GraphQL query string', $rawSchema['description']);
    }

    #[Test]
    public function does_not_emit_request_body_for_routes_without_graphql_profile(): void
    {
        $registry  = $this->customerRegistry();
        $generator = $this->buildGenerator(
            $registry,
            new InMemoryDiscovery([JsonOnlyPayloadFixture5b::class]),
        );

        $paths = $generator->generatePaths();
        $path  = $paths['/phase5b/json-only/{id}'];

        // The fixture only declares Json + JsonLd. No POST and no
        // requestBody on any operation.
        foreach ($path as $methodKey => $op) {
            self::assertArrayNotHasKey(
                'requestBody',
                $op,
                sprintf('%s must not have requestBody — route does not declare GraphQL profile.', strtoupper($methodKey)),
            );
        }
    }

    #[Test]
    public function existing_response_content_map_remains_unchanged_after_request_body_addition(): void
    {
        $registry  = $this->customerRegistry();
        $generator = $this->buildGenerator($registry, new InMemoryDiscovery([GetCustomerPayload::class]));
        $paths     = $generator->generatePaths();

        $getContent  = $paths['/customers/{id}']['get']['responses']['200']['content'];
        $postContent = $paths['/customers/{id}']['post']['responses']['200']['content'];

        // GET and POST share the same response content map (Phase 5b).
        self::assertSame($getContent, $postContent);
        self::assertArrayHasKey('application/json', $getContent);
        self::assertArrayHasKey('application/ld+json', $getContent);
        self::assertArrayHasKey('application/graphql-response+json', $getContent);
    }

    #[Test]
    public function emits_graphql_content_type_when_route_declares_graphql_profile(): void
    {
        $registry  = $this->customerRegistry();
        $generator = $this->buildGenerator($registry, new InMemoryDiscovery([GetCustomerPayload::class]));
        $route     = $generator->describeRoute(GetCustomerPayload::class);

        self::assertNotNull($route);
        $content = $route['operation']['responses']['200']['content'];

        self::assertArrayHasKey('application/graphql-response+json', $content);
        $gql = $content['application/graphql-response+json']['schema'];

        self::assertSame('object', $gql['type']);
        self::assertSame(['data'], $gql['required']);
        self::assertSame(
            ['type' => 'object', 'additionalProperties' => true],
            $gql['properties']['data'],
        );
        self::assertStringContainsString('GraphQL-compatible', $gql['description']);
        self::assertStringContainsString('CustomerResource', $gql['description']);
        self::assertStringContainsString('out of scope', $gql['description']);
    }

    #[Test]
    public function existing_application_json_schema_remains_typed_and_unchanged(): void
    {
        $registry  = $this->customerRegistry();
        $generator = $this->buildGenerator($registry, new InMemoryDiscovery([GetCustomerPayload::class]));
        $route     = $generator->describeRoute(GetCustomerPayload::class);

        self::assertNotNull($route);
        $jsonSchema = $route['operation']['responses']['200']['content']['application/json']['schema'];

        // The Phase 3b typed schema must not have shifted into the looser
        // GraphQL/JSON-LD generic-object shape.
        self::assertSame('object', $jsonSchema['type']);
        self::assertSame(['data'], $jsonSchema['required']);
        self::assertSame(
            ['$ref' => '#/components/schemas/CustomerResource'],
            $jsonSchema['properties']['data'],
        );
    }

    #[Test]
    public function does_not_emit_graphql_content_type_for_routes_without_graphql_profile(): void
    {
        // Synthetic payload that points to the customer response classes
        // but only declares Json + JsonLd (no GraphQL).
        $registry  = $this->customerRegistry();
        $generator = $this->buildGenerator(
            $registry,
            new InMemoryDiscovery([JsonOnlyPayloadFixture5b::class]),
        );

        $route = $generator->describeRoute(JsonOnlyPayloadFixture5b::class);
        self::assertNotNull($route);

        $content = $route['operation']['responses']['200']['content'];
        self::assertArrayHasKey('application/json', $content);
        self::assertArrayNotHasKey('application/graphql-response+json', $content);
    }

    #[Test]
    public function emits_include_query_parameter_for_supports_resource_includes_payload(): void
    {
        $registry  = $this->customerRegistry();
        $generator = $this->buildGenerator($registry, new InMemoryDiscovery([GetCustomerPayload::class]));

        $route = $generator->describeRoute(GetCustomerPayload::class);
        self::assertNotNull($route);

        $params = $route['operation']['parameters'];
        $include = null;
        foreach ($params as $p) {
            if (($p['name'] ?? null) === 'include') {
                $include = $p;
                break;
            }
        }

        self::assertNotNull($include, 'include= query parameter must be emitted for SupportsResourceIncludes payloads.');
        self::assertSame('query', $include['in']);
        self::assertFalse($include['required']);
        self::assertSame('array', $include['schema']['type']);
        self::assertSame('form',  $include['style']);
        self::assertFalse($include['explode']);

        // Phase 6g: the fixture `ProfileResource` gained a resolver-backed
        // `preferences` relation. The metadata-driven include-enum
        // generator naturally emits the dotted token `profile.preferences`
        // alongside the top-level tokens; output stays alphabetically sorted.
        self::assertSame(
            ['addresses', 'profile', 'profile.preferences'],
            $include['schema']['items']['enum'],
            'Tokens must be alphabetically sorted.',
        );
        self::assertStringContainsString('Comma-separated', $include['description']);
    }

    #[Test]
    public function does_not_emit_include_parameter_when_payload_does_not_implement_supports_resource_includes(): void
    {
        // Use a synthetic payload that points to the customer resource but does NOT
        // implement SupportsResourceIncludes — runtime would ignore ?include=, so OpenAPI must too.
        $registry  = $this->customerRegistry();
        $generator = $this->buildGenerator(
            $registry,
            new InMemoryDiscovery([NoIncludesPayloadFixture3c::class]),
        );

        $route = $generator->describeRoute(NoIncludesPayloadFixture3c::class);
        self::assertNotNull($route);

        $names = array_map(static fn ($p) => $p['name'] ?? null, $route['operation']['parameters'] ?? []);
        self::assertNotContains('include', $names, 'Routes whose payload does not implement SupportsResourceIncludes must not advertise include= in OpenAPI.');
    }

    #[Test]
    public function emits_page_and_per_page_query_parameters_for_collection_route(): void
    {
        // Phase 6i: collection routes advertise the pagination
        // parameters on top of the existing `include` and `query`
        // parameters.
        $registry  = $this->customerRegistry();
        $generator = $this->buildGenerator(
            $registry,
            new InMemoryDiscovery([ListCustomersPayload::class]),
        );

        $route = $generator->describeRoute(ListCustomersPayload::class);
        self::assertNotNull($route);

        $params  = $route['operation']['parameters'];
        $byName  = [];
        foreach ($params as $p) {
            $byName[$p['name']] = $p;
        }

        self::assertArrayHasKey('page', $byName);
        self::assertArrayHasKey('perPage', $byName);
        self::assertSame('integer', $byName['page']['schema']['type']);
        self::assertSame(1, $byName['page']['schema']['minimum']);
        self::assertSame(1, $byName['page']['schema']['default']);
        self::assertSame('integer', $byName['perPage']['schema']['type']);
        self::assertSame(1, $byName['perPage']['schema']['minimum']);
        self::assertSame(50, $byName['perPage']['schema']['maximum']);
        self::assertSame(10, $byName['perPage']['schema']['default']);
    }

    #[Test]
    public function does_not_emit_pagination_parameters_on_singular_route(): void
    {
        $registry  = $this->customerRegistry();
        $generator = $this->buildGenerator(
            $registry,
            new InMemoryDiscovery([GetCustomerPayload::class]),
        );

        $route = $generator->describeRoute(GetCustomerPayload::class);
        self::assertNotNull($route);

        $names = array_map(
            static fn ($p) => $p['name'] ?? null,
            $route['operation']['parameters'] ?? [],
        );
        self::assertNotContains('page', $names, 'Singular routes must not advertise ?page=.');
        self::assertNotContains('perPage', $names, 'Singular routes must not advertise ?perPage=.');
    }

    #[Test]
    public function collection_route_response_schema_carries_meta_pagination(): void
    {
        $registry  = $this->customerRegistry();
        $generator = $this->buildGenerator(
            $registry,
            new InMemoryDiscovery([ListCustomersPayload::class]),
        );

        $route   = $generator->describeRoute(ListCustomersPayload::class);
        self::assertNotNull($route);

        $jsonSchema = $route['operation']['responses']['200']['content']['application/json']['schema'];
        self::assertSame(['data', 'meta'], $jsonSchema['required']);
        self::assertSame('array', $jsonSchema['properties']['data']['type']);
        self::assertSame(
            ['pagination'],
            $jsonSchema['properties']['meta']['required'],
        );
        $pagination = $jsonSchema['properties']['meta']['properties']['pagination'];
        self::assertArrayHasKey('oneOf', $pagination);
        self::assertSame(
            ['page', 'perPage', 'total', 'pageCount', 'hasNext', 'hasPrevious'],
            $pagination['oneOf'][0]['required'],
        );
        self::assertSame(
            ['mode', 'perPage', 'total', 'hasNext', 'nextCursor', 'cursor'],
            $pagination['oneOf'][1]['required'],
        );
    }

    #[Test]
    public function emits_sort_query_parameter_for_collection_route(): void
    {
        // Phase 6j: collection routes whose response class declares
        // `#[CollectionSortable]` advertise the bounded `?sort=`
        // parameter. The parameter is plain string (comma-combined
        // forms cannot be enumerated finitely) and lists the
        // allowlist plus the doubled +/- forms in the description.
        $registry  = $this->customerRegistry();
        $generator = $this->buildGenerator(
            $registry,
            new InMemoryDiscovery([ListCustomersPayload::class]),
        );

        $route = $generator->describeRoute(ListCustomersPayload::class);
        self::assertNotNull($route);

        $params = $route['operation']['parameters'];
        $sort   = null;
        foreach ($params as $p) {
            if (($p['name'] ?? null) === 'sort') {
                $sort = $p;
                break;
            }
        }

        self::assertNotNull($sort, 'Collection route with #[CollectionSortable] must advertise ?sort=.');
        self::assertSame('query', $sort['in']);
        self::assertFalse($sort['required']);
        self::assertSame('string', $sort['schema']['type']);
        self::assertStringContainsString('id, name', $sort['description']);
        self::assertStringContainsString('Comma-separated', $sort['description']);
        self::assertStringContainsString('descending', $sort['description']);
        self::assertStringContainsString('HTTP 400', $sort['description']);
    }

    #[Test]
    public function does_not_emit_sort_parameter_on_singular_route(): void
    {
        $registry  = $this->customerRegistry();
        $generator = $this->buildGenerator(
            $registry,
            new InMemoryDiscovery([GetCustomerPayload::class]),
        );

        $route = $generator->describeRoute(GetCustomerPayload::class);
        self::assertNotNull($route);

        $names = array_map(
            static fn ($p) => $p['name'] ?? null,
            $route['operation']['parameters'] ?? [],
        );
        self::assertNotContains('sort', $names, 'Singular routes must not advertise ?sort=.');
    }

    #[Test]
    public function emits_filter_query_parameter_for_collection_route(): void
    {
        // Phase 6k: collection routes whose response class declares
        // `#[CollectionFilterable]` advertise the bounded `?filter=`
        // parameter. The full parameter set on the customer
        // collection is now {include, query, page, perPage, sort,
        // filter}. The parameter is plain string; combinatorics of
        // semicolon-separated terms cannot be enumerated finitely.
        $registry  = $this->customerRegistry();
        $generator = $this->buildGenerator(
            $registry,
            new InMemoryDiscovery([ListCustomersPayload::class]),
        );

        $route = $generator->describeRoute(ListCustomersPayload::class);
        self::assertNotNull($route);

        $params = $route['operation']['parameters'];
        $byName = [];
        foreach ($params as $p) {
            $byName[$p['name']] = $p;
        }

        self::assertArrayHasKey('filter', $byName, 'Collection route with #[CollectionFilterable] must advertise ?filter=.');
        self::assertSame('query', $byName['filter']['in']);
        self::assertFalse($byName['filter']['required']);
        self::assertSame('string', $byName['filter']['schema']['type']);
        self::assertStringContainsString('id [eq|in]', $byName['filter']['description']);
        self::assertStringContainsString('name [eq|contains]', $byName['filter']['description']);
        self::assertStringContainsString('AND semantics', $byName['filter']['description']);
        self::assertStringContainsString('HTTP 400', $byName['filter']['description']);
        self::assertStringContainsString('before sorting and pagination', $byName['filter']['description']);

        // Phase 6j parameters remain.
        self::assertArrayHasKey('include', $byName);
        self::assertArrayHasKey('query',   $byName);
        self::assertArrayHasKey('page',    $byName);
        self::assertArrayHasKey('perPage', $byName);
        self::assertArrayHasKey('sort',    $byName);
    }

    #[Test]
    public function does_not_emit_filter_parameter_on_singular_route(): void
    {
        $registry  = $this->customerRegistry();
        $generator = $this->buildGenerator(
            $registry,
            new InMemoryDiscovery([GetCustomerPayload::class]),
        );

        $route = $generator->describeRoute(GetCustomerPayload::class);
        self::assertNotNull($route);

        $names = array_map(
            static fn ($p) => $p['name'] ?? null,
            $route['operation']['parameters'] ?? [],
        );
        self::assertNotContains('filter', $names, 'Singular routes must not advertise ?filter=.');
    }

    #[Test]
    public function ignores_payloads_without_render_profile_json(): void
    {
        // Use a fixture-only customer that has no AsPayload at all.
        $extractor = new ResourceMetadataExtractor();
        $registry  = ResourceMetadataRegistry::forTesting($extractor);
        $registry->register($extractor->extract(FixtureAddressResource::class));
        $registry->register($extractor->extract(FixtureProfileResource::class));
        $registry->register($extractor->extract(FixtureCustomerResource::class));

        $generator = $this->buildGenerator(
            $registry,
            new InMemoryDiscovery([FixtureCustomerResource::class /* not a payload */]),
        );

        $paths = $generator->generatePaths();
        self::assertSame([], $paths);
    }
}

// InMemoryDiscovery moved to its own file (Semitexa\Api\Tests\Unit\OpenApi\InMemoryDiscovery)
// so single-file phpunit runs (e.g. ai:verify) can autoload it.
