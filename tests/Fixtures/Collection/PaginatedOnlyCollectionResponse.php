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

/** One Way Phase 2 fixture: pagination policy alone — the relaxed emission rule's proof. */
#[ProducesResourceCollection(CustomerResource::class)]
#[CollectionPaginated(defaultPerPage: 7, maxPerPage: 30)]
final class PaginatedOnlyCollectionResponse
{
}
