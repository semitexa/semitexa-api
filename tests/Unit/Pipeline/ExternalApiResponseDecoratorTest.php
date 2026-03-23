<?php

declare(strict_types=1);

namespace Semitexa\Api\Tests\Unit\Pipeline;

use PHPUnit\Framework\TestCase;
use Semitexa\Api\Pipeline\ExternalApiResponseDecorator;
use Semitexa\Core\Discovery\ResolvedRouteMetadata;
use Semitexa\Core\Request;
use Semitexa\Core\Response;

final class ExternalApiResponseDecoratorTest extends TestCase
{
    public function testDecorateInjectsApiVersionHeadersForExternalRoutes(): void
    {
        $decorator = new ExternalApiResponseDecorator();
        $request = new Request('GET', '/api/platform/users', [], [], [], [], []);
        $response = Response::json(['ok' => true]);
        $metadata = new ResolvedRouteMetadata(
            path: '/api/platform/users',
            name: 'platform.users.index',
            methods: ['GET'],
            requestClass: 'Payload',
            responseClass: 'Response',
            produces: ['json'],
            consumes: null,
            handlers: [],
            requirements: [],
            extensions: [
                'external_api' => ['version' => 'v1', 'description' => 'Users list'],
                'api_version' => [
                    'version' => '1.0.0',
                    'deprecated_since' => '2026-05-01',
                    'sunset_date' => '2026-12-01',
                ],
            ],
        );

        $decorated = $decorator->decorate($response, $request, $metadata);

        self::assertSame('1.0.0', $decorated->headers['X-Api-Version']);
        self::assertSame('2026-05-01', $decorated->headers['Deprecation']);
        self::assertSame('2026-12-01', $decorated->headers['Sunset']);
    }

    public function testDecorateLeavesNonExternalRoutesUntouched(): void
    {
        $decorator = new ExternalApiResponseDecorator();
        $request = new Request('GET', '/internal', [], [], [], [], []);
        $response = Response::json(['ok' => true]);
        $metadata = new ResolvedRouteMetadata(
            path: '/internal',
            name: 'internal.index',
            methods: ['GET'],
            requestClass: 'Payload',
            responseClass: 'Response',
            produces: ['json'],
            consumes: null,
            handlers: [],
            requirements: [],
            extensions: [],
        );

        $decorated = $decorator->decorate($response, $request, $metadata);

        self::assertSame($response->headers, $decorated->headers);
    }
}
