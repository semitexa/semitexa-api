<?php

declare(strict_types=1);

namespace Semitexa\Api\Tests\Fixtures\Scalar;

use Semitexa\Core\Resource\Attribute\ResourceField;
use Semitexa\Core\Resource\Attribute\ResourceId;
use Semitexa\Core\Resource\Attribute\ResourceObject;
use Semitexa\Core\Resource\ResourceObjectInterface;

#[ResourceObject(type: 'scalar_types')]
final readonly class ScalarTypesResource implements ResourceObjectInterface
{
    /** @param list<string> $tags */
    public function __construct(
        #[ResourceId]
        public string $id,
        #[ResourceField]
        public bool $active,
        #[ResourceField]
        public int $count,
        #[ResourceField]
        public float $price,
        #[ResourceField]
        public ?int $stock,
        #[ResourceField]
        public string $name,
        #[ResourceField]
        public array $tags,
    ) {
    }
}
