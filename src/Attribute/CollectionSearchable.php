<?php

declare(strict_types=1);

namespace Semitexa\Api\Attribute;

use Attribute;

/**
 * One Way Phase 2: declares free-text search for a collection
 * response — which fields the search term covers and which query
 * parameter carries it. Sibling of `#[CollectionSortable]` /
 * `#[CollectionFilterable]` and, like them, the single source of
 * truth: the runtime parser, the served route contract
 * (`collection.search` + the `role: search` input field), and the
 * OpenAPI documentation all read this one declaration.
 *
 * The parameter defaults to the grid-proven name **`q`**. It is a
 * different concern from GraphQL-subset field selection, which keeps
 * the name `query` (`role: selection`) — both can coexist on one
 * route (design §1.3 disambiguation).
 *
 * Semantics: case-insensitive substring match; a row matches when
 * ANY declared field contains the term (OR across fields). ORM-backed
 * sources push this down as one SQL OR-group of LIKE predicates.
 *
 * Example:
 *
 *     #[ProducesResourceCollection(PingResource::class)]
 *     #[CollectionSearchable(fields: ['label'])]
 *     final class PingCollectionJsonResponse extends JsonResourceResponse
 *     { ... }
 */
#[Attribute(Attribute::TARGET_CLASS)]
final class CollectionSearchable
{
    /**
     * @param list<string> $fields plain scalar resource fields the term covers
     * @param string       $param  the query parameter carrying the term
     */
    public function __construct(
        public readonly array $fields,
        public readonly string $param = 'q',
    ) {
        if ($fields === []) {
            throw new \InvalidArgumentException(
                'CollectionSearchable: at least one searchable field is required.',
            );
        }
        foreach ($fields as $field) {
            if (!is_string($field) || trim($field) === '') {
                throw new \InvalidArgumentException(
                    'CollectionSearchable: field names must be non-empty strings.',
                );
            }
            if (str_contains($field, '.')) {
                throw new \InvalidArgumentException(sprintf(
                    'CollectionSearchable: nested / relation field "%s" is not allowed.',
                    $field,
                ));
            }
        }
        if (trim($param) === '') {
            throw new \InvalidArgumentException('CollectionSearchable: param must be a non-empty string.');
        }
    }
}
