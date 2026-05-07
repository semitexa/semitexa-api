<?php

declare(strict_types=1);

namespace Semitexa\Api\Tests\Fixtures\Customer;

use Semitexa\Api\Attribute\ProducesResourceObject;

/**
 * Test-only response stub. The OpenAPI generator inspects the
 * `#[ProducesResourceObject]` attribute via reflection and never
 * instantiates this class.
 */
#[ProducesResourceObject(CustomerResource::class, description: 'Single customer with optional embedded profile and addresses.')]
final class CustomerJsonResponse
{
}
