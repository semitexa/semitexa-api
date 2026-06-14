<?php

declare(strict_types=1);

namespace Semitexa\Api\Attribute;

use Semitexa\Core\Resource\CollectionPaginationPolicy;
use Semitexa\Core\Resource\Pagination\CollectionPageRequest;
use Attribute;

/**
 * One Way Phase 2: declares the per-route pagination policy for a
 * collection response, replacing the static
 * {@see CollectionPageRequest} bounds where present. Undeclared
 * routes keep today's defaults — the attribute is purely additive.
 *
 * `mode` is a server-side policy (`page` / `cursor` / `auto` /
 * `single` — see {@see CollectionPaginationPolicy}); under `auto`
 * the server answers in page mode while the post-filter total stays
 * within `countThreshold`, else in cursor mode, and the response
 * reports the effective mode in `meta.pagination.mode`.
 *
 * Example:
 *
 *     #[ProducesResourceCollection(PingResource::class)]
 *     #[CollectionPaginated(mode: 'auto', defaultPerPage: 5,
 *         perPageOptions: [5, 10, 25], maxPerPage: 25, countThreshold: 10)]
 *     final class PingCollectionJsonResponse extends JsonResourceResponse
 *     { ... }
 */
#[Attribute(Attribute::TARGET_CLASS)]
final class CollectionPaginated
{
    /** @param list<int> $perPageOptions */
    public function __construct(
        public readonly string $mode = CollectionPaginationPolicy::MODE_PAGE,
        public readonly int $defaultPerPage = CollectionPageRequest::DEFAULT_PER_PAGE,
        public readonly array $perPageOptions = [],
        public readonly int $maxPerPage = CollectionPageRequest::MAX_PER_PAGE,
        public readonly int $countThreshold = 1000,
    ) {
        // Delegate validation to the policy VO — one rule set, two homes
        // would drift. An invalid declaration fails at first resolution.
        $this->toPolicy();
    }

    public function toPolicy(): CollectionPaginationPolicy
    {
        return new CollectionPaginationPolicy(
            mode:           $this->mode,
            defaultPerPage: $this->defaultPerPage,
            perPageOptions: $this->perPageOptions,
            maxPerPage:     $this->maxPerPage,
            countThreshold: $this->countThreshold,
        );
    }
}
