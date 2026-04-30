<?php

declare(strict_types=1);

namespace Semitexa\Api\Tests\Demo\Application\Handler;

use Semitexa\Api\Tests\Fixtures\Demo\Application\Handler\ListArticlesHandler;
use Semitexa\Api\Tests\Fixtures\Demo\Application\Payload\ListArticlesPayload;
use Semitexa\Api\Tests\Fixtures\Demo\Application\Resource\ArticleCollectionResource;
use Semitexa\Api\Tests\Demo\HandlerTestCase;
use Semitexa\Core\Exception\ValidationException;

final class ListArticlesHandlerTest extends HandlerTestCase
{
    public function testReturnsEntireCollectionWhenFilterEmpty(): void
    {
        $handler = $this->makeHandler();
        $payload = new ListArticlesPayload();

        $resource = $handler->handle($payload, new ArticleCollectionResource());

        $context = $resource->getRenderContext();
        self::assertCount(3, $context['data']);
        self::assertSame(['total' => 3, 'published' => 2, 'filter' => null], $context['meta']);
    }

    public function testFiltersToPublishedOnly(): void
    {
        $handler = $this->makeHandler();
        $payload = new ListArticlesPayload();
        $payload->setFilter('published');

        $resource = $handler->handle($payload, new ArticleCollectionResource());
        $context = $resource->getRenderContext();

        self::assertCount(2, $context['data']);
        foreach ($context['data'] as $article) {
            self::assertTrue($article['published']);
        }
        self::assertSame('published', $context['meta']['filter']);
    }

    public function testFiltersToDraftsOnly(): void
    {
        $handler = $this->makeHandler();
        $payload = new ListArticlesPayload();
        $payload->setFilter('drafts');

        $resource = $handler->handle($payload, new ArticleCollectionResource());
        $context = $resource->getRenderContext();

        self::assertCount(1, $context['data']);
        self::assertFalse($context['data'][0]['published']);
        self::assertSame('drafts', $context['meta']['filter']);
    }

    public function testInvalidFilterThrowsValidationException(): void
    {
        $handler = $this->makeHandler();
        $payload = new ListArticlesPayload();
        $payload->setFilter('bogus');

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Validation failed.');

        try {
            $handler->handle($payload, new ArticleCollectionResource());
        } catch (ValidationException $e) {
            self::assertSame(['filter' => ['filter must be one of: published, drafts']], $e->getErrorContext()['errors']);
            self::assertSame(422, $e->getStatusCode()->value);
            self::assertSame('validation', $e->getErrorCode());
            throw $e;
        }
    }

    public function testMetaCountsAreCollectionTotalsNotFilteredTotals(): void
    {
        $handler = $this->makeHandler();
        $payload = new ListArticlesPayload();
        $payload->setFilter('drafts');

        $resource = $handler->handle($payload, new ArticleCollectionResource());

        // total/published reflect the underlying collection, not the filter slice.
        self::assertSame(3, $resource->getRenderContext()['meta']['total']);
        self::assertSame(2, $resource->getRenderContext()['meta']['published']);
    }

    private function makeHandler(): ListArticlesHandler
    {
        $handler = new ListArticlesHandler();
        $this->inject($handler, 'repository', $this->repository);
        return $handler;
    }
}
