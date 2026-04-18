<?php

declare(strict_types=1);

namespace Semitexa\Api\Tests\Unit\Pipeline;

use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Semitexa\Api\Pipeline\ExternalApiExceptionMapper;
use Semitexa\Core\Container\RequestScopedContainer;
use Semitexa\Core\Discovery\ResolvedRouteMetadata;
use Semitexa\Core\Discovery\RouteRegistry;
use Semitexa\Core\Environment;
use Semitexa\Core\Error\ErrorRouteDispatcher;
use Semitexa\Core\Exception\NotFoundException;
use Semitexa\Core\HttpResponse;
use Semitexa\Core\Pipeline\ExceptionMapper;
use Semitexa\Core\Request;

final class ExternalApiExceptionMapperTest extends TestCase
{
    private ?ContainerInterface $emptyContainer = null;

    public function testExternalApiRouteReturnsMachineJsonEnvelope(): void
    {
        $mapper = $this->makeMapper();
        $request = new Request('GET', '/api/users/42', ['Accept' => 'application/json'], [], [], [], []);

        $response = $mapper->map(
            new NotFoundException('User', 42),
            $request,
            $this->makeMetadata(extensions: ['external_api' => ['version' => 'v1']]),
        );

        self::assertSame(404, $response->getStatusCode());
        self::assertSame('application/json', $response->getHeaders()['Content-Type']);

        $decoded = json_decode($response->getContent(), true);
        self::assertIsArray($decoded);
        self::assertArrayHasKey('error', $decoded);
        self::assertSame('User #42 not found.', $decoded['error']['message']);
        self::assertArrayHasKey('code', $decoded['error']);
        self::assertArrayHasKey('context', $decoded['error']);
        self::assertArrayHasKey('request_id', $decoded['error']);
        self::assertArrayHasKey('docs_url', $decoded['error']);
    }

    public function testNonExternalRouteFallsThroughToCoreMapper(): void
    {
        $mapper = $this->makeMapper();
        $request = new Request('GET', '/page', ['Accept' => 'application/json'], [], [], [], []);

        $response = $mapper->map(
            new NotFoundException('Page', 'home'),
            $request,
            $this->makeMetadata(extensions: []),
        );

        // Core envelope: flat {error, message, context}, NOT nested under "error.code"
        $decoded = json_decode($response->getContent(), true);
        self::assertIsArray($decoded);
        self::assertSame(404, $response->getStatusCode());
        self::assertArrayHasKey('message', $decoded);
        self::assertArrayHasKey('context', $decoded);
        self::assertIsString($decoded['error']);
    }

    public function testWithErrorRouteDispatcherForwardsIntoWrappedCoreMapper(): void
    {
        $mapper = $this->makeMapper();
        $ref = new \ReflectionClass($mapper);
        $prop = $ref->getProperty('coreMapper');
        $prop->setAccessible(true);
        $originalCoreMapper = $prop->getValue($mapper);

        $dispatcher = $this->makeDispatcher();
        $decorated = $mapper->withErrorRouteDispatcher($dispatcher);
        $decoratedCoreMapper = $prop->getValue($decorated);

        // Must not mutate the original instance.
        self::assertNotSame($mapper, $decorated);
        self::assertInstanceOf(ExternalApiExceptionMapper::class, $decorated);
        self::assertSame($originalCoreMapper, $prop->getValue($mapper));
        self::assertNotSame($originalCoreMapper, $decoratedCoreMapper);
    }

    private function makeMapper(): ExternalApiExceptionMapper
    {
        return new ExternalApiExceptionMapper(new ExceptionMapper());
    }

    /**
     * @param array<string, mixed> $extensions
     */
    private function makeMetadata(array $extensions): ResolvedRouteMetadata
    {
        return new ResolvedRouteMetadata(
            path: '/',
            name: 'test.route',
            methods: ['GET'],
            requestClass: 'Payload',
            responseClass: 'Response',
            produces: ['json'],
            consumes: null,
            handlers: [],
            requirements: [],
            extensions: $extensions,
        );
    }

    private function makeDispatcher(): ErrorRouteDispatcher
    {
        return new ErrorRouteDispatcher(
            routeRegistry: new RouteRegistry(),
            requestScopedContainer: new RequestScopedContainer($this->makeEmptyContainer()),
            container: $this->makeEmptyContainer(),
            authBootstrapper: null,
            environment: new Environment(
                appEnv: 'prod',
                appDebug: false,
                appName: 'test',
                appHost: 'localhost',
                appPort: 8000,
                swoolePort: 9501,
                swooleSsePort: 9503,
                swooleHost: '127.0.0.1',
                swooleWorkerNum: 1,
                swooleMaxRequest: 1000,
                swooleMaxCoroutine: 1000,
                swooleLogFile: 'var/log/swoole.log',
                swooleLogLevel: 1,
                swooleSessionTableSize: 1024,
                swooleSessionMaxBytes: 65535,
                swooleSseWorkerTableSize: 1024,
                swooleSseDeliverTableSize: 1024,
                swooleSsePayloadMaxBytes: 65535,
                corsAllowOrigin: '*',
                corsAllowMethods: 'GET, POST',
                corsAllowHeaders: 'Content-Type',
                corsAllowCredentials: false,
            ),
            routeExecutor: function (): HttpResponse {
                throw new \RuntimeException('not used in this test');
            },
        );
    }

    private function makeEmptyContainer(): ContainerInterface
    {
        if ($this->emptyContainer instanceof ContainerInterface) {
            return $this->emptyContainer;
        }

        return $this->emptyContainer = new class implements ContainerInterface {
            public function get(string $id): mixed
            {
                throw new class('no') extends \RuntimeException implements \Psr\Container\NotFoundExceptionInterface {};
            }

            public function has(string $id): bool
            {
                return false;
            }
        };
    }
}
