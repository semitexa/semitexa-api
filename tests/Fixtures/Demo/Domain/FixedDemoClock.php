<?php

declare(strict_types=1);

namespace Semitexa\Api\Tests\Fixtures\Demo\Domain;

use DateTimeImmutable;

/**
 * Test-only DemoClock that returns a pinned instant. Not container-managed —
 * instantiate directly in tests and inject via Reflection where needed.
 */
final class FixedDemoClock extends DemoClock
{
    public function __construct(private readonly DateTimeImmutable $instant) {}

    public function now(): DateTimeImmutable
    {
        return $this->instant;
    }
}
