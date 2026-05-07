<?php

declare(strict_types=1);

namespace Semitexa\Api\Tests\Fixtures\Customer;

use Semitexa\Core\Attribute\AsService;
use Semitexa\Core\Resource\RelationResolverInterface;
use Semitexa\Core\Resource\RenderContext;

/**
 * Test-only resolver for `CustomerResource::$profile`. Returns no
 * data — OpenAPI generation only inspects metadata, never invokes
 * resolveBatch.
 */
#[AsService]
final class CustomerProfileResolver implements RelationResolverInterface
{
    public function resolveBatch(array $parents, RenderContext $ctx): array
    {
        return [];
    }
}
