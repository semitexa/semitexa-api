<?php

declare(strict_types=1);

namespace Semitexa\Api\Tests\Demo\Application\Handler;

use Semitexa\Api\Tests\Fixtures\Demo\Application\Handler\GetArticleHandler;
use Semitexa\Api\Tests\Fixtures\Demo\Application\Payload\GetArticlePayload;
use Semitexa\Api\Tests\Fixtures\Demo\Application\Resource\ArticleResource;
use Semitexa\Api\Tests\Demo\HandlerTestCase;
use Semitexa\Core\Exception\NotFoundException;

final class GetArticleHandlerTest extends HandlerTestCase
{
    public function testReturnsArticlePayload(): void
    {
        $handler = $this->makeHandler();
        $payload = new GetArticlePayload();
        $payload->setId('art_00001');

        $resource = $handler->handle($payload, new ArticleResource());
        $context = $resource->getRenderContext();

        self::assertArrayHasKey('data', $context);
        self::assertSame('art_00001', $context['data']['id']);
        self::assertSame('Welcome to Semitexa API', $context['data']['title']);
        self::assertTrue($context['data']['published']);
        self::assertNotEmpty($context['data']['createdAt']);
    }

    public function testThrowsNotFoundExceptionForUnknownId(): void
    {
        $handler = $this->makeHandler();
        $payload = new GetArticlePayload();
        $payload->setId('art_does_not_exist');

        try {
            $handler->handle($payload, new ArticleResource());
            self::fail('expected NotFoundException');
        } catch (NotFoundException $e) {
            self::assertSame(404, $e->getStatusCode()->value);
            self::assertSame('not_found', $e->getErrorCode());
            self::assertSame('Article #art_does_not_exist not found.', $e->getMessage());
        }
    }

    private function makeHandler(): GetArticleHandler
    {
        $handler = new GetArticleHandler();
        $this->inject($handler, 'repository', $this->repository);
        return $handler;
    }
}
