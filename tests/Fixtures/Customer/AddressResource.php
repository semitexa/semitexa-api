<?php

declare(strict_types=1);

namespace Semitexa\Api\Tests\Fixtures\Customer;

use Semitexa\Core\Resource\Attribute\ResourceField;
use Semitexa\Core\Resource\Attribute\ResourceId;
use Semitexa\Core\Resource\Attribute\ResourceObject;
use Semitexa\Core\Resource\ResourceObjectInterface;

#[ResourceObject(type: 'address')]
final readonly class AddressResource implements ResourceObjectInterface
{
    public function __construct(
        #[ResourceId]
        public string $id,
        #[ResourceField]
        public string $city,
        #[ResourceField]
        public string $line1,
    ) {
    }
}
