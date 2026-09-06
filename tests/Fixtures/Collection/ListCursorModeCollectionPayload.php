<?php

declare(strict_types=1);

namespace Semitexa\Api\Tests\Fixtures\Collection;

use Semitexa\Authorization\Attribute\AsProtectedPayload;
use Semitexa\Core\Resource\RenderProfile;

/**
 * A collection route pinned to cursor mode, so the OpenAPI generator tests can
 * assert the `?cursor=` parameter is advertised where the runtime accepts it —
 * the other half of the gate that keeps it off page- and single-mode routes.
 */
#[AsProtectedPayload(
    path: '/cursor-collection',
    methods: ['GET'],
    renderProfile: [RenderProfile::Json],
    responseWith: CursorModeCollectionResponse::class,
)]
final class ListCursorModeCollectionPayload
{
}
