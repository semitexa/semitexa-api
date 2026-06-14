<?php

declare(strict_types=1);

namespace Semitexa\Api\Discovery;

use ReflectionClass;
use Semitexa\Api\Attribute\CollectionFilterable;
use Semitexa\Api\Attribute\CollectionFilterOptions;
use Semitexa\Api\Attribute\CollectionPaginated;
use Semitexa\Api\Attribute\CollectionSearchable;
use Semitexa\Api\Attribute\CollectionSortable;
use Semitexa\Api\Attribute\ProducesResourceCollection;
use Semitexa\Api\Attribute\ProducesResourceObject;
use Semitexa\Core\Attribute\AsService;
use Semitexa\Core\Attribute\SatisfiesServiceContract;
use Semitexa\Core\Attribute\WatchScopes;
use Semitexa\Core\Contract\CollectionAwareContributorInterface;
use Semitexa\Core\Contract\RouteContractBlockContributorInterface;
use Semitexa\Core\Resource\CollectionPaginationPolicy;
use Semitexa\Core\Resource\Pagination\CollectionPageRequest;

/**
 * One Way Pattern — Phase 1+2: semitexa-api's `collection` block contributor.
 *
 * Projects the collection allowlist attributes on the response class —
 * `#[CollectionSortable]` / `#[CollectionFilterable]` (Phase 1) plus
 * `#[CollectionSearchable]` / `#[CollectionPaginated]` /
 * `#[CollectionFilterOptions]` (Phase 2) — into the route contract
 * document. Routes without `#[CollectionPaginated]` keep the static
 * {@see CollectionPageRequest} bounds, surfaced with EXACTLY the Phase 1
 * keys so their served documents stay byte-identical; a declared policy
 * adds `modes` / `perPageOptions` / `countThreshold` per-route.
 *
 * Degradation rule (design §1.1): a response class with none of the
 * collection attributes contributes nothing — the contract document then
 * equals today's OPTIONS shape plus the `input` block. The block is also
 * withheld for non-collection responses (no `#[ProducesResourceCollection]`):
 * the allowlists are collection vocabulary and would be dishonest on a
 * singular route.
 *
 * Doubles as the response→resource link resolver for the core assembler:
 * `#[ProducesResourceCollection]` / `#[ProducesResourceObject]` are api
 * vocabulary, and core must not read them itself.
 */
#[AsService]
#[SatisfiesServiceContract(of: RouteContractBlockContributorInterface::class)]
final class CollectionContractBlockContributor implements
    RouteContractBlockContributorInterface,
    CollectionAwareContributorInterface
{
    public function contributeBlocks(string $payloadClass, ?string $responseClass): array
    {
        if ($responseClass === null || !class_exists($responseClass)) {
            return [];
        }

        $ref = new ReflectionClass($responseClass);
        if ($ref->getAttributes(ProducesResourceCollection::class) === []) {
            return [];
        }

        $sortFields    = $this->sortAllowlistFor($ref);
        $filterFields  = $this->filterAllowlistFor($ref);
        $searchable    = $this->searchableFor($ref);
        $paginated     = $this->paginatedFor($ref);
        $optionFields  = $this->filterOptionFieldsFor($ref);
        $liveScopes    = $this->watchScopesFor($payloadClass);
        if ($sortFields === [] && $filterFields === [] && $searchable === null
            && $paginated === null && $optionFields === [] && $liveScopes === []
        ) {
            return [];
        }

        if ($paginated !== null) {
            $policy = $paginated->toPolicy();
            $pagination = [
                'modes'          => self::advertisedModes($policy->mode),
                'defaultPage'    => CollectionPageRequest::DEFAULT_PAGE,
                'defaultPerPage' => $policy->defaultPerPage,
                'maxPerPage'     => $policy->maxPerPage,
            ];
            if ($policy->perPageOptions !== []) {
                $pagination['perPageOptions'] = $policy->perPageOptions;
            }
            if ($policy->mode === CollectionPaginationPolicy::MODE_AUTO) {
                $pagination['countThreshold'] = $policy->countThreshold;
            }
        } else {
            // Phase 1 shape, verbatim — undeclared routes stay byte-identical.
            $pagination = [
                'defaultPage'    => CollectionPageRequest::DEFAULT_PAGE,
                'defaultPerPage' => CollectionPageRequest::DEFAULT_PER_PAGE,
                'maxPerPage'     => CollectionPageRequest::MAX_PER_PAGE,
            ];
        }

        $collection = ['pagination' => $pagination];
        if ($sortFields !== []) {
            $collection['sort'] = ['fields' => $sortFields];
        }
        if ($filterFields !== []) {
            $collection['filter'] = ['fields' => $filterFields];
        }
        if ($searchable !== null) {
            $collection['search'] = [
                'param'  => $searchable->param,
                'fields' => array_values($searchable->fields),
            ];
        }
        if ($optionFields !== []) {
            foreach ($optionFields as $field) {
                if (!array_key_exists($field, $filterFields)) {
                    throw new \LogicException(sprintf(
                        '%s declares #[CollectionFilterOptions] field "%s" that is not in its #[CollectionFilterable] allowlist.',
                        $responseClass,
                        $field,
                    ));
                }
            }
            $collection['filterOptions'] = ['fields' => $optionFields];
        }
        if ($liveScopes !== []) {
            // One Way Phase 4: the payload's #[WatchScopes] declaration,
            // projected so a metadata-driven client can see WHY the feed is
            // live (the same keys the held-open SSE subscription watches).
            $collection['live'] = ['scopes' => $liveScopes];
        }

        return ['collection' => $collection];
    }

    /**
     * The feed payload's declared `#[WatchScopes]` invalidation scope keys —
     * read from the PAYLOAD class (the route carrier), unlike the collection
     * allowlists which ride the response class: the watch list is a property
     * of the route's live serving, not of the envelope shape.
     *
     * @return list<string>
     */
    private function watchScopesFor(string $payloadClass): array
    {
        if (!class_exists($payloadClass)) {
            return [];
        }

        $attrs = (new ReflectionClass($payloadClass))->getAttributes(WatchScopes::class);
        if ($attrs === []) {
            return [];
        }

        /** @var WatchScopes $declared */
        $declared = $attrs[0]->newInstance();

        return $declared->scopes;
    }

    public function resolveResourceClass(?string $responseClass): ?string
    {
        if ($responseClass === null || !class_exists($responseClass)) {
            return null;
        }

        $ref = new ReflectionClass($responseClass);

        // Collection wins when both are present — same precedence as the
        // OpenAPI route generator.
        $collectionAttrs = $ref->getAttributes(ProducesResourceCollection::class);
        if ($collectionAttrs !== []) {
            /** @var ProducesResourceCollection $produces */
            $produces = $collectionAttrs[0]->newInstance();

            return class_exists($produces->resourceClass) ? $produces->resourceClass : null;
        }

        $objectAttrs = $ref->getAttributes(ProducesResourceObject::class);
        if ($objectAttrs !== []) {
            /** @var ProducesResourceObject $produces */
            $produces = $objectAttrs[0]->newInstance();

            return class_exists($produces->resourceClass) ? $produces->resourceClass : null;
        }

        return null;
    }

    /**
     * A response is a collection iff it declares `#[ProducesResourceCollection]`
     * — true even when it carries no sort/filter/pagination attributes (a bare
     * collection that contributes no `collection` block). This is the reliable
     * cardinality signal {@see RouteContract::$isCollection} surfaces.
     */
    public function resolvesCollection(?string $responseClass): bool
    {
        if ($responseClass === null || !class_exists($responseClass)) {
            return false;
        }

        return (new ReflectionClass($responseClass))->getAttributes(ProducesResourceCollection::class) !== [];
    }

    /**
     * @param ReflectionClass<object> $ref
     * @return list<string>
     */
    private function sortAllowlistFor(ReflectionClass $ref): array
    {
        $attrs = $ref->getAttributes(CollectionSortable::class);
        if ($attrs === []) {
            return [];
        }
        /** @var CollectionSortable $sortable */
        $sortable = $attrs[0]->newInstance();

        return array_values($sortable->fields);
    }

    /**
     * @param ReflectionClass<object> $ref
     * @return array<string, list<string>>
     */
    private function filterAllowlistFor(ReflectionClass $ref): array
    {
        $attrs = $ref->getAttributes(CollectionFilterable::class);
        if ($attrs === []) {
            return [];
        }
        /** @var CollectionFilterable $filterable */
        $filterable = $attrs[0]->newInstance();

        return $filterable->fields;
    }

    /** @param ReflectionClass<object> $ref */
    private function searchableFor(ReflectionClass $ref): ?CollectionSearchable
    {
        $attrs = $ref->getAttributes(CollectionSearchable::class);

        return $attrs === [] ? null : $attrs[0]->newInstance();
    }

    /** @param ReflectionClass<object> $ref */
    private function paginatedFor(ReflectionClass $ref): ?CollectionPaginated
    {
        $attrs = $ref->getAttributes(CollectionPaginated::class);

        return $attrs === [] ? null : $attrs[0]->newInstance();
    }

    /**
     * @param ReflectionClass<object> $ref
     * @return list<string>
     */
    private function filterOptionFieldsFor(ReflectionClass $ref): array
    {
        $attrs = $ref->getAttributes(CollectionFilterOptions::class);
        if ($attrs === []) {
            return [];
        }
        /** @var CollectionFilterOptions $options */
        $options = $attrs[0]->newInstance();

        return array_values($options->fields);
    }

    /**
     * What the route can answer in, projected from the declared policy:
     * `auto` advertises all three (the server flips page↔cursor by
     * threshold, and an explicit `?cursor=` is always honored); a pinned
     * mode advertises itself alone.
     *
     * @return list<string>
     */
    private static function advertisedModes(string $declaredMode): array
    {
        return $declaredMode === CollectionPaginationPolicy::MODE_AUTO
            ? [
                CollectionPaginationPolicy::MODE_PAGE,
                CollectionPaginationPolicy::MODE_CURSOR,
                CollectionPaginationPolicy::MODE_AUTO,
            ]
            : [$declaredMode];
    }
}
