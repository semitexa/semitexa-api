<?php

declare(strict_types=1);

namespace Semitexa\Api\Attributes;

use Attribute;

/**
 * Marks a Payload DTO as an external API endpoint.
 *
 * When semitexa-api is installed, routes carrying this attribute:
 * - receive machine-facing JSON error envelopes (via ExternalApiExceptionMapper)
 * - expose version and deprecation headers when combined with #[ApiVersion]
 * - are enumerable through RouteInspectionRegistryInterface for OpenAPI export
 *
 * Routes that do NOT carry this attribute keep full Core-default semantics.
 * Activation is strictly opt-in.
 *
 * Usage:
 * ```php
 * #[AsPayload(path: '/api/v1/orders', methods: ['GET'], responseWith: ResourceResponse::class)]
 * #[ExternalApi(version: 'v1')]
 * class OrderListPayload { ... }
 * ```
 */
#[Attribute(Attribute::TARGET_CLASS)]
final class ExternalApi
{
    public function __construct(
        /** Canonical API version string for this endpoint (e.g. 'v1', 'v2'). */
        public readonly string $version = 'v1',
        /** Optional human-readable description shown in API documentation. */
        public readonly string $description = '',
    ) {}
}
