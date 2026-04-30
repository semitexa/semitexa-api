<?php

declare(strict_types=1);

namespace Semitexa\Api\Tests\Demo;

use Semitexa\Api\Tests\Fixtures\Demo\Application\Handler\CreateArticleHandler;
use Semitexa\Api\Tests\Fixtures\Demo\Application\Handler\GetArticleHandler;
use Semitexa\Api\Tests\Fixtures\Demo\Application\Handler\ListArticlesHandler;
use Semitexa\Api\Tests\Fixtures\Demo\Application\Payload\CreateArticlePayload;
use Semitexa\Api\Tests\Fixtures\Demo\Application\Payload\DeleteArticlePayload;
use Semitexa\Api\Tests\Fixtures\Demo\Application\Payload\GetArticlePayload;
use Semitexa\Api\Tests\Fixtures\Demo\Application\Payload\ListArticlesPayload;
use Semitexa\Api\Tests\Fixtures\Demo\Application\Payload\PatchArticlePayload;
use Semitexa\Api\Tests\Fixtures\Demo\Application\Payload\ReplaceArticlePayload;
use Semitexa\Api\Tests\Fixtures\Demo\Application\Resource\ArticleResource;
use Semitexa\Api\Tests\Fixtures\Demo\Application\Resource\ArticleCollectionResource;
use Semitexa\Api\Pipeline\ExternalApiExceptionMapper;
use Semitexa\Api\Pipeline\ExternalApiResponseDecorator;
use Semitexa\Core\Discovery\ResolvedRouteMetadata;
use Semitexa\Core\HttpResponse;
use Semitexa\Core\Request;

/**
 * End-to-end integration: handler-thrown domain exception → ExternalApiExceptionMapper
 * → consistent machine JSON envelope; happy-path resource → ExternalApiResponseDecorator
 * → X-Api-Version header.
 *
 * The demo Payloads carry #[ExternalApi] + #[ApiVersion]; the resolver writes those
 * into ResolvedRouteMetadata extensions. Here we forge the metadata explicitly
 * (matching the resolver's output) and prove the contract end-to-end.
 */
final class EnvelopeIntegrationTest extends HandlerTestCase
{
    public function testNotFoundExceptionFromGetHandlerBecomesMachineEnvelope(): void
    {
        $handler = new GetArticleHandler();
        $this->inject($handler, 'repository', $this->repository);

        $payload = new GetArticlePayload();
        $payload->setId('art_does_not_exist');

        $caught = null;
        try {
            $handler->handle($payload, new ArticleResource());
        } catch (\Throwable $e) {
            $caught = $e;
        }
        self::assertNotNull($caught, 'expected a domain exception from the handler');

        $mapper = new ExternalApiExceptionMapper();
        $request = new Request(
            'GET',
            '/api/v1/demo/articles/art_does_not_exist',
            ['Accept' => 'application/json', 'X-Request-Id' => 'req-test-1'],
            [], [], [], [],
        );
        $response = $mapper->map($caught, $request, $this->makeApiMetadata('GET', '/api/v1/demo/articles/{id}'));

        self::assertSame(404, $response->getStatusCode());
        self::assertSame('application/json', $response->getHeaders()['Content-Type']);

        $decoded = json_decode($response->getContent(), true);
        self::assertSame('not_found', $decoded['error']['code']);
        self::assertSame('Article #art_does_not_exist not found.', $decoded['error']['message']);
        self::assertSame('req-test-1', $decoded['error']['request_id']);
        self::assertNull($decoded['error']['docs_url']);
    }

    public function testValidationExceptionFromCreateHandlerBecomesMachineEnvelopeWithFieldErrors(): void
    {
        $handler = new CreateArticleHandler();
        $this->inject($handler, 'repository', $this->repository);
        $this->inject($handler, 'clock', $this->clock);

        $payload = new CreateArticlePayload();
        $payload->setTitle('');
        $payload->setBody('content but no title');

        try {
            $handler->handle($payload, new ArticleResource());
            self::fail('expected ValidationException');
        } catch (\Throwable $e) {
            $caught = $e;
        }

        $mapper = new ExternalApiExceptionMapper();
        $request = new Request('POST', '/api/v1/demo/articles', [], [], [], [], []);
        $response = $mapper->map($caught, $request, $this->makeApiMetadata('POST', '/api/v1/demo/articles'));

        self::assertSame(422, $response->getStatusCode());

        $decoded = json_decode($response->getContent(), true);
        self::assertSame('validation', $decoded['error']['code']);
        self::assertSame('Validation failed.', $decoded['error']['message']);
        self::assertArrayHasKey('errors', $decoded['error']['context']);
        self::assertArrayHasKey('title', $decoded['error']['context']['errors']);
        self::assertNull($decoded['error']['request_id'], 'no X-Request-Id header → null');
    }

    public function testResponseDecoratorAttachesVersionHeaderForExternalRoutes(): void
    {
        $handler = new ListArticlesHandler();
        $this->inject($handler, 'repository', $this->repository);

        $resource = $handler->handle(new ListArticlesPayload(), new ArticleCollectionResource());
        // Encode body the way ResponseRenderer would.
        $body = json_encode($resource->getRenderContext(), JSON_UNESCAPED_UNICODE);
        $coreResponse = new HttpResponse(content: $body, statusCode: 200, headers: ['Content-Type' => 'application/json']);

        $decorator = new ExternalApiResponseDecorator();
        $request = new Request('GET', '/api/v1/demo/articles', ['Accept' => 'application/json'], [], [], [], []);
        $decorated = $decorator->decorate($coreResponse, $request, $this->makeApiMetadata('GET', '/api/v1/demo/articles'));

        $headers = $decorated->getHeaders();
        self::assertArrayHasKey('X-Api-Version', $headers);
        self::assertSame('1.0.0', $headers['X-Api-Version']);
        self::assertArrayNotHasKey('Deprecation', $headers, 'no deprecation date set in fixture');
        self::assertArrayNotHasKey('Sunset', $headers, 'no sunset date set in fixture');
    }

    public function testDecoratorIsNoOpForNonExternalRoutes(): void
    {
        $decorator = new ExternalApiResponseDecorator();
        $request = new Request('GET', '/some-page', [], [], [], [], []);
        $original = HttpResponse::html('<h1>page</h1>');
        $result = $decorator->decorate($original, $request, $this->makeNonApiMetadata());

        self::assertArrayNotHasKey('X-Api-Version', $result->getHeaders());
    }

    public function testSurveyOfDeclaredApiAttributesAcrossDemoPayloads(): void
    {
        // Walk every demo payload and confirm it carries #[ExternalApi] + #[ApiVersion].
        // This is the contract the showcase relies on; if a payload silently loses the
        // attribute the demo would degrade to plain Core mapping.
        $payloads = [
            ListArticlesPayload::class,
            GetArticlePayload::class,
            CreateArticlePayload::class,
            ReplaceArticlePayload::class,
            PatchArticlePayload::class,
            DeleteArticlePayload::class,
        ];

        foreach ($payloads as $payloadClass) {
            $ref = new \ReflectionClass($payloadClass);

            $external = $ref->getAttributes(\Semitexa\Api\Attribute\ExternalApi::class);
            self::assertNotEmpty($external, "{$payloadClass} is missing #[ExternalApi]");
            self::assertSame('v1', $external[0]->newInstance()->version);

            $version = $ref->getAttributes(\Semitexa\Api\Attribute\ApiVersion::class);
            self::assertNotEmpty($version, "{$payloadClass} is missing #[ApiVersion]");
            self::assertSame('1.0.0', $version[0]->newInstance()->version);

            $public = $ref->getAttributes(\Semitexa\Authorization\Attribute\PublicEndpoint::class);
            self::assertNotEmpty($public, "{$payloadClass} is missing #[PublicEndpoint] (the demo is unauthenticated)");
        }
    }

    /** @return array{path: string, method: string} */
    public function _route(string $payloadClass): array
    {
        $ref = new \ReflectionClass($payloadClass);
        $attr = $ref->getAttributes(\Semitexa\Core\Attribute\AsPayload::class)[0]->newInstance();
        return ['path' => $attr->path ?? '', 'method' => ($attr->methods ?? ['GET'])[0]];
    }

    private function makeApiMetadata(string $method, string $path): ResolvedRouteMetadata
    {
        return new ResolvedRouteMetadata(
            path: $path,
            name: 'api.demo.test',
            methods: [$method],
            requestClass: 'Payload',
            responseClass: 'Resource',
            produces: ['application/json'],
            consumes: $method === 'GET' || $method === 'DELETE' ? null : ['application/json'],
            handlers: [],
            requirements: [],
            extensions: [
                'external_api' => ['version' => 'v1', 'description' => ''],
                'api_version'  => ['version' => '1.0.0', 'deprecated_since' => null, 'sunset_date' => null],
            ],
        );
    }

    private function makeNonApiMetadata(): ResolvedRouteMetadata
    {
        return new ResolvedRouteMetadata(
            path: '/some-page',
            name: 'page',
            methods: ['GET'],
            requestClass: 'Payload',
            responseClass: 'Resource',
            produces: ['text/html'],
            consumes: null,
            handlers: [],
            requirements: [],
            extensions: [],
        );
    }
}
