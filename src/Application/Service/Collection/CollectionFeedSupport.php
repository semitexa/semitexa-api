<?php

declare(strict_types=1);

namespace Semitexa\Api\Application\Service\Collection;

use ReflectionClass;
use Semitexa\Api\Attribute\CollectionFilterable;
use Semitexa\Api\Attribute\CollectionPaginated;
use Semitexa\Api\Attribute\CollectionSearchable;
use Semitexa\Api\Attribute\CollectionSortable;
use Semitexa\Core\Attribute\AsService;
use Semitexa\Core\Resource\CollectionCriteria;
use Semitexa\Core\Resource\CollectionPaginationPolicy;
use Semitexa\Core\Resource\Exception\InvalidCursorException;
use Semitexa\Core\Resource\Exception\InvalidPaginationException;
use Semitexa\Core\Resource\Filter\CollectionFilterRequest;
use Semitexa\Core\Resource\Pagination\CollectionPageRequest;
use Semitexa\Core\Resource\Sort\CollectionSortRequest;

/**
 * One Way Phase 2: the thin handler-side parse seam for canonical
 * collection routes — raw query strings in, a validated
 * {@see CollectionCriteria} out, so a collection handler body shrinks
 * to "bind source, return rows".
 *
 * Reads the response class's collection declarations
 * (`#[CollectionSortable]` / `#[CollectionFilterable]` /
 * `#[CollectionSearchable]` / `#[CollectionPaginated]`) — the same
 * single source of truth the route contract and OpenAPI project — and
 * funnels every raw parameter through the phase-6 parsers, so all
 * error posture is the established typed-400 envelope.
 *
 * Mode policing at parse time (per the declared policy):
 *   - `page`   — `?cursor=` rejected (the route never advertises it).
 *   - `cursor` — `?page=` rejected.
 *   - `auto`   — both accepted; an explicit `?page=` is honored only
 *                while the total is within `countThreshold` (the
 *                compiler enforces that half — it needs the count).
 *   - `single` — both rejected.
 *   - undeclared — both accepted (today's customers behavior).
 * `?cursor=` and `?page=` together are always rejected (Phase 6l).
 */
#[AsService]
final class CollectionFeedSupport
{
    /**
     * Per-response-class collection declarations, resolved once. `criteriaFor()`
     * runs on EVERY canonical collection request, and the four declarations it
     * reads (`#[CollectionPaginated]` / `#[CollectionSearchable]` /
     * `#[CollectionSortable]` / `#[CollectionFilterable]`) are static per class —
     * so reflecting them on every request is pure waste. Memoize per worker,
     * mirroring {@see \Semitexa\Api\Application\Service\ApiRouteMetadataResolver}.
     *
     * @var array<class-string, array{policy: CollectionPaginationPolicy, searchable: ?CollectionSearchable, sort: list<string>, filter: array<string, list<string>>}>
     */
    private static array $declarationsCache = [];

    public function criteriaFor(
        string $responseClass,
        ?string $rawQ = null,
        ?string $rawSort = null,
        ?string $rawFilter = null,
        ?string $rawPage = null,
        ?string $rawPerPage = null,
        ?string $rawCursor = null,
    ): CollectionCriteria {
        $declarations = $this->declarationsFor($responseClass);

        $policy     = $declarations['policy'];
        $searchable = $declarations['searchable'];

        $cursor           = self::trimToNull($rawCursor);
        $pageWasRequested = self::trimToNull($rawPage) !== null;

        if ($cursor !== null && $pageWasRequested) {
            throw new InvalidCursorException(
                '?cursor= and ?page= are mutually exclusive — use one or the other',
            );
        }
        if ($policy->declared) {
            if ($cursor !== null && $policy->mode === CollectionPaginationPolicy::MODE_PAGE) {
                throw new InvalidCursorException('this route is page-mode only and does not accept ?cursor=');
            }
            if ($pageWasRequested && $policy->mode === CollectionPaginationPolicy::MODE_CURSOR) {
                throw new InvalidPaginationException('page', (string) $rawPage, 'this route is cursor-mode only');
            }
            if ($policy->mode === CollectionPaginationPolicy::MODE_SINGLE && ($cursor !== null || $pageWasRequested)) {
                throw new InvalidPaginationException(
                    $pageWasRequested ? 'page' : 'cursor',
                    $pageWasRequested ? (string) $rawPage : (string) $rawCursor,
                    'this route serves the whole collection in a single response',
                );
            }
        }

        $sort = CollectionSortRequest::fromQueryParam(
            self::trimToNull($rawSort),
            $declarations['sort'],
        );
        $filter = CollectionFilterRequest::fromQueryParam(
            self::trimToNull($rawFilter),
            $declarations['filter'],
        );
        $page = CollectionPageRequest::fromQueryParams(
            self::trimToNull($rawPage),
            self::trimToNull($rawPerPage),
            $policy->defaultPerPage,
            $policy->maxPerPage,
        );

        // `q` only carries meaning when the route declares search; routes
        // without `#[CollectionSearchable]` have no search param in their
        // payload contract, so a stray value is simply not search intent.
        $q = $searchable !== null ? self::trimToNull($rawQ) : null;

        return new CollectionCriteria(
            page:             $page,
            sort:             $sort,
            filter:           $filter,
            q:                $q,
            searchFields:     $searchable !== null ? array_values($searchable->fields) : [],
            cursor:           $cursor,
            policy:           $policy,
            pageWasRequested: $pageWasRequested,
        );
    }

    /**
     * Resolve (and memoize) the four collection declarations for a response
     * class. Reflection happens once per class per worker; every subsequent
     * request reuses the cached result.
     *
     * @param class-string $responseClass
     * @return array{policy: CollectionPaginationPolicy, searchable: ?CollectionSearchable, sort: list<string>, filter: array<string, list<string>>}
     */
    private function declarationsFor(string $responseClass): array
    {
        if (isset(self::$declarationsCache[$responseClass])) {
            return self::$declarationsCache[$responseClass];
        }

        $ref = new ReflectionClass($responseClass);

        return self::$declarationsCache[$responseClass] = [
            'policy'     => $this->policyFor($ref),
            'searchable' => $this->searchableFor($ref),
            'sort'       => $this->sortAllowlistFor($ref),
            'filter'     => $this->filterAllowlistFor($ref),
        ];
    }

    /** @param ReflectionClass<object> $ref */
    private function policyFor(ReflectionClass $ref): CollectionPaginationPolicy
    {
        $attrs = $ref->getAttributes(CollectionPaginated::class);
        if ($attrs === []) {
            return CollectionPaginationPolicy::default();
        }
        /** @var CollectionPaginated $paginated */
        $paginated = $attrs[0]->newInstance();

        return $paginated->toPolicy();
    }

    /** @param ReflectionClass<object> $ref */
    private function searchableFor(ReflectionClass $ref): ?CollectionSearchable
    {
        $attrs = $ref->getAttributes(CollectionSearchable::class);

        return $attrs === [] ? null : $attrs[0]->newInstance();
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

    private static function trimToNull(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }
        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
    }
}
