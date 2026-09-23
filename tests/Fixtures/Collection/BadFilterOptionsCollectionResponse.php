<?php

declare(strict_types=1);

namespace Semitexa\Api\Tests\Fixtures\Collection;

use Semitexa\Api\Attribute\CollectionFilterable;
use Semitexa\Api\Attribute\CollectionFilterOptions;
use Semitexa\Api\Attribute\ProducesResourceCollection;
use Semitexa\Api\Tests\Fixtures\Customer\CustomerResource;

/** One Way Phase 2 fixture: misdeclared — option field outside the filter allowlist. */
#[ProducesResourceCollection(CustomerResource::class)]
#[CollectionFilterable(['name' => ['eq']])]
#[CollectionFilterOptions(['status'])]
final class BadFilterOptionsCollectionResponse
{
}
