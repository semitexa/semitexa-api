<?php

declare(strict_types=1);

namespace Semitexa\Api\Tests\Demo\Application\Handler;

use DateTimeImmutable;
use Semitexa\Api\Tests\Fixtures\Demo\Application\Handler\PatchArticleHandler;
use Semitexa\Api\Tests\Fixtures\Demo\Application\Payload\PatchArticlePayload;
use Semitexa\Api\Tests\Fixtures\Demo\Application\Resource\ArticleResource;
use Semitexa\Api\Tests\Fixtures\Demo\Domain\DemoClock;
use Semitexa\Api\Tests\Fixtures\Demo\Domain\FixedDemoClock;
use Semitexa\Api\Tests\Demo\HandlerTestCase;
use Semitexa\Core\Exception\NotFoundException;
use Semitexa\Core\Exception\ValidationException;

final class PatchArticleHandlerTest extends HandlerTestCase
{
    public function testPatchesOnlyProvidedFields(): void
    {
        $laterClock = new FixedDemoClock(new DateTimeImmutable('2026-01-15T14:00:00Z'));
        $handler = $this->makeHandler($laterClock);

        $original = $this->repository->find('art_00001');
        self::assertNotNull($original);

        $payload = new PatchArticlePayload();
        $payload->setId('art_00001');
        $payload->setPublished(false); // only "published" provided

        $resource = $handler->handle($payload, new ArticleResource());
        $article = $resource->getRenderContext()['data'];

        // Only published changed; title/body preserved.
        self::assertSame($original->title, $article['title']);
        self::assertSame($original->body, $article['body']);
        self::assertFalse($article['published']);
        self::assertStringStartsWith('2026-01-15T14:00:00', $article['updatedAt']);
    }

    public function testEmptyPatchThrowsValidation(): void
    {
        $handler = $this->makeHandler();
        $payload = new PatchArticlePayload();
        $payload->setId('art_00001');
        // No fields set.

        try {
            $handler->handle($payload, new ArticleResource());
            self::fail('expected ValidationException');
        } catch (ValidationException $e) {
            self::assertArrayHasKey('_', $e->getErrorContext()['errors']);
        }
    }

    public function testProvidedEmptyTitleThrowsValidation(): void
    {
        $handler = $this->makeHandler();
        $payload = new PatchArticlePayload();
        $payload->setId('art_00001');
        $payload->setTitle(''); // explicitly empty → invalid

        try {
            $handler->handle($payload, new ArticleResource());
            self::fail('expected ValidationException');
        } catch (ValidationException $e) {
            self::assertArrayHasKey('title', $e->getErrorContext()['errors']);
        }
    }

    public function testThrowsNotFoundForUnknownId(): void
    {
        $handler = $this->makeHandler();
        $payload = new PatchArticlePayload();
        $payload->setId('art_does_not_exist');
        $payload->setBody('something');

        $this->expectException(NotFoundException::class);
        $handler->handle($payload, new ArticleResource());
    }

    public function testCanPatchAllThreeFieldsSimultaneously(): void
    {
        $handler = $this->makeHandler();
        $payload = new PatchArticlePayload();
        $payload->setId('art_00001');
        $payload->setTitle('PT');
        $payload->setBody('PB');
        $payload->setPublished(false);

        $article = $handler->handle($payload, new ArticleResource())->getRenderContext()['data'];

        self::assertSame('PT', $article['title']);
        self::assertSame('PB', $article['body']);
        self::assertFalse($article['published']);
    }

    private function makeHandler(?DemoClock $clock = null): PatchArticleHandler
    {
        $handler = new PatchArticleHandler();
        $this->inject($handler, 'repository', $this->repository);
        $this->inject($handler, 'clock', $clock ?? $this->clock);
        return $handler;
    }
}
