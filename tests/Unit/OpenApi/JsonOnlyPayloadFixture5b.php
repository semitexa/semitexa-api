<?php

declare(strict_types=1);

namespace Semitexa\Api\Tests\Unit\OpenApi;

use Semitexa\Api\Tests\Fixtures\Customer\CustomerJsonLdResponse;
use Semitexa\Api\Tests\Fixtures\Customer\CustomerJsonResponse;
use Semitexa\Core\Attribute\AsPayload;
use Semitexa\Core\Resource\IncludeSet;
use Semitexa\Core\Resource\RenderProfile;
use Semitexa\Core\Resource\SupportsResourceIncludes;

/**
 * Phase 5b negative-test fixture: a payload that declares Json + JsonLd but
 * NOT GraphQL. Used to prove the OpenAPI generator does not advertise
 * `application/graphql-response+json` for routes that don't declare the
 * GraphQL profile.
 */
#[AsPayload(
    path: '/phase5b/json-only/{id}',
    methods: ['GET'],
    responseWith: CustomerJsonResponse::class,
    renderProfile: [RenderProfile::Json, RenderProfile::JsonLd],
    responsesByProfile: [
        'json'    => CustomerJsonResponse::class,
        'json-ld' => CustomerJsonLdResponse::class,
    ],
)]
final class JsonOnlyPayloadFixture5b implements SupportsResourceIncludes
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

    public function includes(): IncludeSet
    {
        return IncludeSet::empty();
    }
}
