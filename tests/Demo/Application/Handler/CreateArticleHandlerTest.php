<?php

declare(strict_types=1);

namespace Semitexa\Api\Tests\Demo\Application\Handler;

use Semitexa\Api\Tests\Fixtures\Demo\Application\Handler\CreateArticleHandler;
use Semitexa\Api\Tests\Fixtures\Demo\Application\Payload\CreateArticlePayload;
use Semitexa\Api\Tests\Fixtures\Demo\Application\Resource\ArticleResource;
use Semitexa\Api\Tests\Demo\HandlerTestCase;
use Semitexa\Core\Exception\ValidationException;

final class CreateArticleHandlerTest extends HandlerTestCase
{
    public function testCreatesArticleWithStatus201AndPersistsIt(): void
    {
        $handler = $this->makeHandler();
        $payload = new CreateArticlePayload();
        $payload->setTitle('A new entry');
        $payload->setBody('Hello world');
        $payload->setPublished(true);

        $resource = $handler->handle($payload, new ArticleResource());

        self::assertSame(201, $resource->getStatusCode());

        $article = $resource->getRenderContext()['data'];
        self::assertNotEmpty($article['id']);
        self::assertSame('A new entry', $article['title']);
        self::assertTrue($article['published']);

        // Persisted in the repository:
        $stored = $this->repository->find($article['id']);
        self::assertNotNull($stored);
        self::assertSame('A new entry', $stored->title);
    }

    public function testEmptyTitleThrowsValidationException(): void
    {
        $handler = $this->makeHandler();
        $payload = new CreateArticlePayload();
        $payload->setTitle('');
        $payload->setBody('Whatever');

        try {
            $handler->handle($payload, new ArticleResource());
            self::fail('expected ValidationException');
        } catch (ValidationException $e) {
            self::assertSame(422, $e->getStatusCode()->value);
            self::assertArrayHasKey('title', $e->getErrorContext()['errors']);
        }
    }

    public function testTitleLongerThan200ThrowsValidationException(): void
    {
        $handler = $this->makeHandler();
        $payload = new CreateArticlePayload();
        $payload->setTitle(str_repeat('a', 201));
        $payload->setBody('ok');

        $this->expectException(ValidationException::class);
        $handler->handle($payload, new ArticleResource());
    }

    public function testCreateUsesInjectedClockForTimestamps(): void
    {
        $handler = $this->makeHandler();
        $payload = new CreateArticlePayload();
        $payload->setTitle('Time test');
        $payload->setBody('check');

        $resource = $handler->handle($payload, new ArticleResource());
        $article = $resource->getRenderContext()['data'];

        // Clock is fixed at 2026-01-15T12:00:00Z by HandlerTestCase::setUp.
        self::assertStringStartsWith('2026-01-15T12:00:00', $article['createdAt']);
        self::assertSame($article['createdAt'], $article['updatedAt']);
    }

    private function makeHandler(): CreateArticleHandler
    {
        $handler = new CreateArticleHandler();
        $this->inject($handler, 'repository', $this->repository);
        $this->inject($handler, 'clock', $this->clock);
        return $handler;
    }
}
