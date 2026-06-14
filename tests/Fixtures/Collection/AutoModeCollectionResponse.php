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

/** One Way Phase 2 fixture: full stanza — auto mode, search, filter options. */
#[ProducesResourceCollection(CustomerResource::class)]
#[CollectionSortable(['name', 'id'])]
#[CollectionFilterable(['name' => ['eq', 'contains'], 'id' => ['eq', 'in']])]
#[CollectionSearchable(fields: ['name'])]
#[CollectionFilterOptions(['name'])]
#[CollectionPaginated(mode: 'auto', defaultPerPage: 5, perPageOptions: [5, 10, 25], maxPerPage: 25, countThreshold: 10)]
final class AutoModeCollectionResponse
{
}
