<?php

declare(strict_types=1);

namespace Semitexa\Api\Attribute;

use Attribute;

/**
 * Phase 6j: declares the explicit sort allowlist for a collection
 * response. Read by both the OpenAPI route generator (to emit the
 * `sort` query parameter description) and the route's runtime
 * handler (to validate the parsed `?sort=` request).
 *
 * The attribute is the single source of truth: a single list per
 * response class, mirrored in the wire API documentation and in the
 * runtime parser, so they cannot drift.
 *
 * Example:
 *
 *     #[ProducesResourceCollection(CustomerResource::class)]
 *     #[CollectionSortable(['id', 'name'])]
 *     final class CustomerCollectionJsonResponse extends JsonResourceResponse
 *     implements ResourceInterface
 *     { ... }
 *
 * Field names must be plain scalar fields on the resource — the
 * parser rejects dotted / nested paths. An empty list disables sort
 * for the route (the parameter is not advertised).
 */
#[Attribute(Attribute::TARGET_CLASS)]
final class CollectionSortable
{
    /** @param list<string> $fields explicit allowlist; order matters for OpenAPI docs */
    public function __construct(
        public readonly array $fields,
    ) {
    }
}
