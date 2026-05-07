<?php

declare(strict_types=1);

namespace Semitexa\Api\Attribute;

use Attribute;

/**
 * Phase 6k: declares the explicit filter allowlist for a collection
 * response — a `field => list<operator>` map. Read by both the
 * OpenAPI route generator (to emit the `?filter=` parameter
 * description) and the route's runtime handler (to validate the
 * parsed `?filter=` request).
 *
 * Sibling to `#[CollectionSortable]`. Like that attribute, this is
 * the single source of truth: the runtime parser and the wire
 * documentation read the same map, so they cannot drift.
 *
 * Example:
 *
 *     #[ProducesResourceCollection(CustomerResource::class)]
 *     #[CollectionSortable(['id', 'name'])]
 *     #[CollectionFilterable([
 *         'id'   => ['eq', 'in'],
 *         'name' => ['eq', 'contains'],
 *     ])]
 *     final class CustomerCollectionJsonResponse extends JsonResourceResponse
 *     implements ResourceInterface
 *     { ... }
 *
 * Field names must be plain scalar fields on the resource — the
 * parser rejects dotted / nested paths. An empty map disables filter
 * for the route (the parameter is not advertised). Operators are the
 * wire-form strings declared on
 * `Semitexa\Core\Resource\Filter\FilterOperator` (`eq`, `in`,
 * `contains`).
 */
#[Attribute(Attribute::TARGET_CLASS)]
final class CollectionFilterable
{
    /** @param array<string, list<string>> $fields field => list of operator wire forms */
    public function __construct(
        public readonly array $fields,
    ) {
    }
}
