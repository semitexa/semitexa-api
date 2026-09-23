<?php

declare(strict_types=1);

namespace Semitexa\Api\Tests\Fixtures\Collection;

use Semitexa\Api\Attribute\CollectionPaginated;
use Semitexa\Api\Attribute\ProducesResourceCollection;
use Semitexa\Api\Tests\Fixtures\Customer\CustomerResource;

/** One Way Phase 2 fixture: single-response mode. */
#[ProducesResourceCollection(CustomerResource::class)]
#[CollectionPaginated(mode: 'single')]
final class SingleModeCollectionResponse
{
}
