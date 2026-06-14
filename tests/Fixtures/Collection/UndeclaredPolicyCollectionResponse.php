<?php

declare(strict_types=1);

namespace Semitexa\Api\Tests\Fixtures\Collection;

use Semitexa\Api\Attribute\CollectionFilterable;
use Semitexa\Api\Attribute\CollectionFilterOptions;
use Semitexa\Api\Attribute\CollectionPaginated;
use Semitexa\Api\Attribute\CollectionSearchable;
use Semitexa\Api\Attribute\CollectionSortable;
use Semitexa\Api\Attribute\ProducesResourceCollection;
use Semitexa\Api\Tests\Fixtures\Customer\CustomerResource;

/** One Way Phase 2 fixture: Phase 1 shape — no Phase 2 attributes at all. */
#[ProducesResourceCollection(CustomerResource::class)]
#[CollectionSortable(['name'])]
final class UndeclaredPolicyCollectionResponse
{
}
