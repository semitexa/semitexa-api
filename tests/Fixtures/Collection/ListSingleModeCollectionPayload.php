<?php

declare(strict_types=1);

namespace Semitexa\Api\Tests\Fixtures\Collection;

use Semitexa\Authorization\Attribute\AsProtectedPayload;
use Semitexa\Core\Resource\RenderProfile;

/**
 * A collection route pinned to single mode: it answers everything at once and
 * the runtime rejects BOTH `?page=` and `?cursor=`, so neither may be
 * documented.
 */
#[AsProtectedPayload(
    path: '/single-collection',
    methods: ['GET'],
    renderProfile: [RenderProfile::Json],
    responseWith: SingleModeCollectionResponse::class,
)]
final class ListSingleModeCollectionPayload
{
}
