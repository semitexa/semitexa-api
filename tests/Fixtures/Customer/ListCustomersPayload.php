<?php

declare(strict_types=1);

namespace Semitexa\Api\Tests\Fixtures\Customer;

use Semitexa\Authorization\Attribute\AsProtectedPayload;
use Semitexa\Core\Resource\IncludeSet;
use Semitexa\Core\Resource\RenderProfile;
use Semitexa\Core\Resource\SupportsResourceIncludes;

/**
 * Test-only collection payload mirroring `GET /customers`. Used by
 * OpenAPI generator tests to exercise collection routes that declare
 * pagination, sort, and filter parameters.
 */
#[AsProtectedPayload(
    path: '/customers',
    methods: ['GET'],
    responseWith: CustomerCollectionJsonResponse::class,
    renderProfile: [RenderProfile::Json, RenderProfile::JsonLd, RenderProfile::GraphQL],
    responsesByProfile: [
        'json'    => CustomerCollectionJsonResponse::class,
        'json-ld' => CustomerCollectionJsonLdResponse::class,
        'graphql' => CustomerCollectionGraphqlResponse::class,
    ],
)]
final class ListCustomersPayload implements SupportsResourceIncludes
{
    private string $rawIncludes = '';
    private string $rawQuery = '';

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
