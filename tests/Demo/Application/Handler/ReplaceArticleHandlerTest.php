<?php

declare(strict_types=1);

namespace Semitexa\Api\Tests\Demo\Application\Handler;

use DateTimeImmutable;
use Semitexa\Api\Tests\Fixtures\Demo\Application\Handler\ReplaceArticleHandler;
use Semitexa\Api\Tests\Fixtures\Demo\Application\Payload\ReplaceArticlePayload;
use Semitexa\Api\Tests\Fixtures\Demo\Application\Resource\ArticleResource;
use Semitexa\Api\Tests\Fixtures\Demo\Domain\DemoClock;
use Semitexa\Api\Tests\Fixtures\Demo\Domain\FixedDemoClock;
use Semitexa\Api\Tests\Demo\HandlerTestCase;
use Semitexa\Core\Exception\NotFoundException;
use Semitexa\Core\Exception\ValidationException;

final class ReplaceArticleHandlerTest extends HandlerTestCase
{
    public function testReplacesEveryMutableField(): void
    {
        // Use a later clock so updatedAt is observably distinct.
        $laterClock = new FixedDemoClock(new DateTimeImmutable('2026-01-15T13:00:00Z'));
        $handler = $this->makeHandler($laterClock);

        $payload = new ReplaceArticlePayload();
        $payload->setId('art_00001');
        $payload->setTitle('Replaced title');
        $payload->setBody('Replaced body');
        $payload->setPublished(false);

        $resource = $handler->handle($payload, new ArticleResource());
        $article = $resource->getRenderContext()['data'];

        self::assertSame('art_00001', $article['id']);
        self::assertSame('Replaced title', $article['title']);
        self::assertSame('Replaced body', $article['body']);
        self::assertFalse($article['published']);
        self::assertStringStartsWith('2026-01-15T13:00:00', $article['updatedAt']);

        $stored = $this->repository->find('art_00001');
        self::assertNotNull($stored);
        self::assertSame('Replaced title', $stored->title);
    }

    public function testThrowsNotFoundForUnknownId(): void
    {
        $handler = $this->makeHandler();
        $payload = new ReplaceArticlePayload();
        $payload->setId('art_does_not_exist');
        $payload->setTitle('x');
        $payload->setBody('y');

        $this->expectException(NotFoundException::class);
        $handler->handle($payload, new ArticleResource());
    }

    public function testEmptyTitleThrowsValidation(): void
    {
        $handler = $this->makeHandler();
        $payload = new ReplaceArticlePayload();
        $payload->setId('art_00001');
        $payload->setTitle('');
        $payload->setBody('non-empty');

        try {
            $handler->handle($payload, new ArticleResource());
            self::fail('expected ValidationException');
        } catch (ValidationException $e) {
            self::assertArrayHasKey('title', $e->getErrorContext()['errors']);
        }
    }

    public function testEmptyBodyThrowsValidation(): void
    {
        $handler = $this->makeHandler();
        $payload = new ReplaceArticlePayload();
        $payload->setId('art_00001');
        $payload->setTitle('non-empty');
        $payload->setBody('');

        try {
            $handler->handle($payload, new ArticleResource());
            self::fail('expected ValidationException');
        } catch (ValidationException $e) {
            self::assertArrayHasKey('body', $e->getErrorContext()['errors']);
        }
    }

    public function testValidationRunsBeforeNotFoundLookup(): void
    {
        // If both validation and not-found apply, validation should win because it's
        // a 4xx-class client error visible without resource access.
        $handler = $this->makeHandler();
        $payload = new ReplaceArticlePayload();
        $payload->setId('art_does_not_exist');
        $payload->setTitle('');
        $payload->setBody('');

        $this->expectException(ValidationException::class);
        $handler->handle($payload, new ArticleResource());
    }

    private function makeHandler(?DemoClock $clock = null): ReplaceArticleHandler
    {
        $handler = new ReplaceArticleHandler();
        $this->inject($handler, 'repository', $this->repository);
        $this->inject($handler, 'clock', $clock ?? $this->clock);
        return $handler;
    }
}
