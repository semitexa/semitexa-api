<?php

declare(strict_types=1);

namespace Semitexa\Api\Application\Service\Collection;

use Semitexa\Api\Domain\Model\Collection\CollectionDeclarations;
use Semitexa\Core\Resource\CollectionPaginationPolicy;
use Semitexa\Core\Resource\Pagination\CollectionPageRequest;

/**
 * The route contract's `collection` block — pagination, sort, filter, search,
 * filter options and live scopes — from declarations, wherever they came from.
 * One builder, so a field-driven feed and an attribute-declared route describe
 * themselves identically.
 */
final class CollectionContractBlock
{
    /**
     * @param list<string> $liveScopes the feed's watch scopes
     * @return array<string, mixed>|null null when there is nothing to describe
     */
    public static function build(CollectionDeclarations $declarations, array $liveScopes = []): ?array
    {
        if ($declarations->isEmpty() && $liveScopes === []) {
            return null;
        }

        $policy = $declarations->policy;
        if ($policy->declared) {
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
        if ($declarations->sort !== []) {
            $collection['sort'] = ['fields' => $declarations->sort];
        }
        if ($declarations->filter !== []) {
            $collection['filter'] = ['fields' => $declarations->filter];
        }
        if ($declarations->searchable !== null) {
            $collection['search'] = [
                'param'  => $declarations->searchable->param,
                'fields' => array_values($declarations->searchable->fields),
            ];
        }
        if ($declarations->filterOptions !== []) {
            $collection['filterOptions'] = ['fields' => $declarations->filterOptions];
        }
        if ($liveScopes !== []) {
            // The payload's watch scopes, projected so a metadata-driven client
            // can see WHY the feed is live (the keys its subscription watches).
            $collection['live'] = ['scopes' => $liveScopes];
        }

        return $collection;
    }

    /** @return list<string> */
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
