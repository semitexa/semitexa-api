<?php

declare(strict_types=1);

namespace Semitexa\Api\Tests\Fixtures\Union;

use Semitexa\Core\Resource\Attribute\ResourceField;
use Semitexa\Core\Resource\Attribute\ResourceId;
use Semitexa\Core\Resource\Attribute\ResourceObject;
use Semitexa\Core\Resource\ResourceObjectInterface;

/**
 * Shared union-fixture leaf used by both `IncludeTokenCollectorTest` and
 * `ResourceSchemaGeneratorTest` so polymorphic-relation behaviour is
 * exercised against one canonical pair of variants. The basename and
 * `type` discriminator are deliberately short — `ResourceSchemaGenerator`
 * derives OpenAPI envelope names from the basename and discriminator
 * mapping keys from the `type`, and the schema test pins both.
 */
#[ResourceObject(type: 'user')]
final readonly class UserResource implements ResourceObjectInterface
{
    public function __construct(
        #[ResourceId]
        public string $id,
        #[ResourceField]
        public string $name,
    ) {
    }
}
