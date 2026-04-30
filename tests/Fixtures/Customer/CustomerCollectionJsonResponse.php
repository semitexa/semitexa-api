<?php

declare(strict_types=1);

namespace Semitexa\Api\Tests\Fixtures\Customer;

use Semitexa\Api\Attribute\CollectionFilterable;
use Semitexa\Api\Attribute\CollectionSortable;
use Semitexa\Api\Attribute\ProducesResourceCollection;

/**
 * Test-only collection response stub. The OpenAPI generator inspects
 * the attributes via reflection and never instantiates this class.
 */
#[ProducesResourceCollection(CustomerResource::class, description: 'List of customers with optional embedded profile and addresses (resolver-batched).')]
#[CollectionSortable(['id', 'name'])]
#[CollectionFilterable([
    'id'   => ['eq', 'in'],
    'name' => ['eq', 'contains'],
])]
final class CustomerCollectionJsonResponse
{
}
