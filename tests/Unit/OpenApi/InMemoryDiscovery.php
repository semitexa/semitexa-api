<?php

declare(strict_types=1);

namespace Semitexa\Api\Tests\Unit\OpenApi;

use Semitexa\Core\Discovery\ClassDiscovery;

/**
 * Stub ClassDiscovery for OpenAPI generator tests. Returns a fixed list when
 * asked for #[AsPayload] (or any other attribute) classes, so the generator
 * can be tested in isolation without booting the live composer classmap.
 */
final class InMemoryDiscovery extends ClassDiscovery
{
    /** @param list<class-string> $classes */
    public function __construct(private readonly array $classes)
    {
    }

    public function findClassesWithAttribute(string $attributeClass): array
    {
        return $this->classes;
    }
}
