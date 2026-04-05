<?php

declare(strict_types=1);

namespace Semitexa\Api\Tests\Unit\Discovery;

use PHPUnit\Framework\TestCase;
use ReflectionProperty;
use Semitexa\Api\Attribute\ApiVersion;
use Semitexa\Api\Attribute\ExternalApi;
use Semitexa\Api\Discovery\ApiRouteMetadataResolver;
use Semitexa\Core\Discovery\DefaultRouteMetadataResolver;

final class ApiRouteMetadataResolverTest extends TestCase
{
    public function testResolveEnrichesCoreMetadataWithExternalApiExtensions(): void
    {
        $resolver = new ApiRouteMetadataResolver();
        $property = new ReflectionProperty($resolver, 'coreResolver');
        $property->setValue($resolver, new DefaultRouteMetadataResolver());

        $metadata = $resolver->resolve([
            'path' => '/api/test',
            'name' => 'api.test',
            'methods' => ['GET'],
            'class' => ApiRouteMetadataResolverFixturePayload::class,
            'responseClass' => 'Semitexa\\Core\\Http\\Response\\ResourceResponse',
            'handlers' => [],
            'requirements' => [],
        ]);

        self::assertTrue($metadata->hasExtension('external_api'));
        self::assertSame('v7', $metadata->extensions['external_api']['version']);
        self::assertSame('Fixture endpoint', $metadata->extensions['external_api']['description']);
        self::assertSame('7.1.0', $metadata->extensions['api_version']['version']);
        self::assertSame('2026-03-01', $metadata->extensions['api_version']['deprecated_since']);
        self::assertSame('2026-09-01', $metadata->extensions['api_version']['sunset_date']);
    }
}

#[ExternalApi(version: 'v7', description: 'Fixture endpoint')]
#[ApiVersion(version: '7.1.0', deprecatedSince: '2026-03-01', sunsetDate: '2026-09-01')]
final class ApiRouteMetadataResolverFixturePayload
{
}
