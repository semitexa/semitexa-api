<?php

declare(strict_types=1);

namespace Semitexa\Api\Tests\Demo;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;
use Semitexa\Api\Tests\Fixtures\Demo\Domain\DemoClock;
use Semitexa\Api\Tests\Fixtures\Demo\Domain\FixedDemoClock;
use Semitexa\Api\Tests\Fixtures\Demo\Domain\InMemoryArticleRepository;

/**
 * Shared bootstrap for handler tests.
 *
 * Demo handlers and the repository receive their dependencies via
 * #[InjectAsReadonly] property attributes (set by the runtime container). In
 * unit tests we mimic this by writing the protected properties directly via
 * Reflection — same pattern used by the existing semitexa-api unit tests.
 */
abstract class HandlerTestCase extends TestCase
{
    protected DemoClock $clock;
    protected InMemoryArticleRepository $repository;

    protected function setUp(): void
    {
        $this->clock = new FixedDemoClock(new DateTimeImmutable('2026-01-15T12:00:00Z'));
        $this->repository = new InMemoryArticleRepository();
        $this->inject($this->repository, 'clock', $this->clock);
        $this->repository->reset();
    }

    protected function inject(object $target, string $property, mixed $value): void
    {
        $ref = new ReflectionProperty($target, $property);
        $ref->setValue($target, $value);
    }
}
