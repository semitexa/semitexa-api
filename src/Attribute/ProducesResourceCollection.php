<?php

declare(strict_types=1);

namespace Semitexa\Api\Attribute;

use Attribute;

/**
 * Phase 6h: declares that a resource-response subclass produces a
 * **collection** of `ResourceObjectInterface` items (one per element
 * of `$data` in the rendered envelope) instead of a single resource.
 * Read by the OpenAPI route generator to emit the JSON collection
 * envelope shape:
 *
 *     {
 *       "type": "object",
 *       "required": ["data"],
 *       "properties": {
 *         "data": {
 *           "type": "array",
 *           "items": {"$ref": "#/components/schemas/<Resource>"}
 *         }
 *       }
 *     }
 *
 * Sibling to `#[ProducesResourceObject]` — pick one per response
 * class. The two attributes are mutually exclusive; declaring both
 * is a configuration error the generator surfaces by preferring the
 * collection shape and emitting nothing for the singular shape.
 *
 * Example:
 *
 *     #[ProducesResourceCollection(CustomerResource::class)]
 *     final class CustomerCollectionJsonResponse extends JsonResourceResponse implements ResourceInterface
 *     { ... }
 */
#[Attribute(Attribute::TARGET_CLASS)]
final class ProducesResourceCollection
{
    /** @param class-string $resourceClass */
    public function __construct(
        public readonly string $resourceClass,
        public readonly string $description = '',
    ) {
    }
}
