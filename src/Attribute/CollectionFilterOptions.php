<?php

declare(strict_types=1);

namespace Semitexa\Api\Attribute;

use Attribute;

/**
 * One Way Phase 2: declares which `#[CollectionFilterable]` fields
 * ship server-fed select options in the response envelope
 * (`meta.filterOptions: { field: [{value, label}] }`).
 *
 * Declared, not implied — only routes that opt in pay the per-request
 * query cost of computing the option lists. The handler supplies the
 * actual values (via `JsonResourceResponse::withResources()`); this
 * attribute is the contract-side availability declaration the route
 * contract projects as `collection.filterOptions.fields`.
 *
 * Every declared field must also be in the route's
 * `#[CollectionFilterable]` allowlist — options for a field a client
 * cannot filter by would be dead weight; the contributor enforces it.
 */
#[Attribute(Attribute::TARGET_CLASS)]
final class CollectionFilterOptions
{
    /** @param list<string> $fields filterable fields with server-fed options */
    public function __construct(
        public readonly array $fields,
    ) {
        if ($fields === []) {
            throw new \InvalidArgumentException(
                'CollectionFilterOptions: at least one field is required.',
            );
        }
        foreach ($fields as $field) {
            if (!is_string($field) || trim($field) === '') {
                throw new \InvalidArgumentException(
                    'CollectionFilterOptions: field names must be non-empty strings.',
                );
            }
        }
    }
}
