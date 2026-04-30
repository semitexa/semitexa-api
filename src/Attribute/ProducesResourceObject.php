<?php

declare(strict_types=1);

namespace Semitexa\Api\Attribute;

use Attribute;

/**
 * Declares which Resource DTO class a JsonResourceResponse subclass produces
 * at runtime. Read by the OpenAPI generator (Phase 3b) to wire the route's
 * 200 response schema to the right component.
 *
 * The attribute is a small, explicit bridge instead of guessing the
 * Resource DTO class from handler bodies.
 *
 * Example:
 *
 *     #[ProducesResourceObject(CustomerResource::class)]
 *     final class CustomerJsonResponse extends JsonResourceResponse implements ResourceInterface
 *     { ... }
 */
#[Attribute(Attribute::TARGET_CLASS)]
final class ProducesResourceObject
{
    /** @param class-string $resourceClass */
    public function __construct(
        public readonly string $resourceClass,
        public readonly string $description = '',
    ) {
    }
}
