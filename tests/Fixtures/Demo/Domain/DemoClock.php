<?php

declare(strict_types=1);

namespace Semitexa\Api\Tests\Fixtures\Demo\Domain;

use DateTimeImmutable;
use Semitexa\Core\Attribute\AsService;

/**
 * Wall-clock indirection so tests can pin time deterministically.
 *
 * Container-managed: parameterless. Tests use the FixedDemoClock subclass to
 * supply a fixed instant; the runtime always returns the current time.
 */
#[AsService]
class DemoClock
{
    public function now(): DateTimeImmutable
    {
        return new DateTimeImmutable();
    }
}
