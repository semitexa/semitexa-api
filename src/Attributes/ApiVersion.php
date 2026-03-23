<?php

declare(strict_types=1);

namespace Semitexa\Api\Attributes;

use Attribute;

/**
 * Declares versioning and deprecation metadata for an external API endpoint.
 *
 * When combined with #[ExternalApi], RouteExecutor injects the following headers
 * into responses for the marked route:
 * - X-Api-Version: {version}
 * - Deprecation: {deprecatedSince}  (only when deprecatedSince is set)
 * - Sunset: {sunsetDate}            (only when sunsetDate is set)
 *
 * These headers follow the IETF Sunset and Deprecation header drafts so API
 * consumers can detect and react to versioning events automatically.
 *
 * Usage:
 * ```php
 * #[AsPayload(path: '/api/v1/users', methods: ['GET'], responseWith: GenericResponse::class)]
 * #[ExternalApi(version: 'v1')]
 * #[ApiVersion(version: '1.0.0', deprecatedSince: '2026-06-01', sunsetDate: '2026-12-01')]
 * class UserListPayload { ... }
 * ```
 */
#[Attribute(Attribute::TARGET_CLASS)]
final class ApiVersion
{
    public function __construct(
        /** Semantic version of this endpoint (e.g. '1.0.0', '2.3.1'). */
        public readonly string $version,
        /**
         * ISO-8601 date when this endpoint was deprecated (e.g. '2026-06-01').
         * Null means not deprecated.
         */
        public readonly ?string $deprecatedSince = null,
        /**
         * ISO-8601 date after which this endpoint will be removed (e.g. '2026-12-01').
         * Null means no planned removal.
         */
        public readonly ?string $sunsetDate = null,
    ) {}

    public function isDeprecated(): bool
    {
        return $this->deprecatedSince !== null;
    }
}
