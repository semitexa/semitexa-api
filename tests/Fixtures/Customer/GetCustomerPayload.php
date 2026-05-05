<?php

declare(strict_types=1);

namespace Semitexa\Api\Tests\Fixtures\Customer;

use Semitexa\Authorization\Attribute\AsProtectedPayload;
use Semitexa\Core\Resource\IncludeSet;
use Semitexa\Core\Resource\RenderProfile;
use Semitexa\Core\Resource\SupportsResourceIncludes;

/**
 * Test-only payload mirroring the runtime contract for
 * `GET|POST /customers/{id}`. Used by OpenAPI generator tests to
 * exercise singular routes that declare Json + JsonLd + GraphQL
 * render profiles.
 */
#[AsProtectedPayload(
    path: '/customers/{id}',
    methods: ['GET', 'POST'],
    responseWith: CustomerJsonResponse::class,
    renderProfile: [RenderProfile::Json, RenderProfile::JsonLd, RenderProfile::GraphQL],
    responsesByProfile: [
        'json'    => CustomerJsonResponse::class,
        'json-ld' => CustomerJsonLdResponse::class,
        'graphql' => CustomerGraphqlResponse::class,
    ],
)]
final class GetCustomerPayload implements SupportsResourceIncludes
{
    private string $id = '';
    private string $rawIncludes = '';
    private string $rawQuery = '';

    public function getId(): string
    {
        return $this->id;
    }

    public function setId(string $v): void
    {
        $this->id = $v;
    }

    public function getInclude(): string
    {
        return $this->rawIncludes;
    }

    public function setInclude(string $v): void
    {
        $this->rawIncludes = $v;
    }

    public function getQuery(): string
    {
        return $this->rawQuery;
    }

    public function setQuery(string $v): void
    {
        $this->rawQuery = $v;
    }

    public function includes(): IncludeSet
    {
        return IncludeSet::fromQueryString($this->rawIncludes);
    }
}
