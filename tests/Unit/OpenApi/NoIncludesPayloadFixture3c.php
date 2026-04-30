<?php

declare(strict_types=1);

namespace Semitexa\Api\Tests\Unit\OpenApi;

use Semitexa\Api\Tests\Fixtures\Customer\CustomerJsonResponse;
use Semitexa\Core\Attribute\AsPayload;
use Semitexa\Core\Resource\RenderProfile;

/**
 * Phase 3c test fixture: a JSON-resource payload that deliberately does NOT
 * implement `SupportsResourceIncludes`. Used to prove the OpenAPI generator
 * omits the `include=` parameter when runtime would ignore the query string.
 */
#[AsPayload(
    path: '/phase3c/no-includes/{id}',
    methods: ['GET'],
    responseWith: CustomerJsonResponse::class,
    renderProfile: RenderProfile::Json,
)]
final class NoIncludesPayloadFixture3c
{
    private string $id = '';

    public function getId(): string
    {
        return $this->id;
    }

    public function setId(string $v): void
    {
        $this->id = $v;
    }
}
