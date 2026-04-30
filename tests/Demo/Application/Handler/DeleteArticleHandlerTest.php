<?php

declare(strict_types=1);

namespace Semitexa\Api\Tests\Demo\Application\Handler;

use Semitexa\Api\Tests\Fixtures\Demo\Application\Handler\DeleteArticleHandler;
use Semitexa\Api\Tests\Fixtures\Demo\Application\Payload\DeleteArticlePayload;
use Semitexa\Api\Tests\Fixtures\Demo\Application\Resource\ArticleDeletedResource;
use Semitexa\Api\Tests\Demo\HandlerTestCase;

final class DeleteArticleHandlerTest extends HandlerTestCase
{
    public function testDeletesExistingArticleAndReturnsNoContent(): void
    {
        $handler = $this->makeHandler();
        $payload = new DeleteArticlePayload();
        $payload->setId('art_00001');

        self::assertNotNull($this->repository->find('art_00001'));

        $resource = $handler->handle($payload, new ArticleDeletedResource());

        self::assertSame(204, $resource->getStatusCode());
        self::assertSame([], $resource->getRenderContext(), 'no body for 204');
        self::assertNull($this->repository->find('art_00001'), 'article was actually deleted');
    }

    public function testIsIdempotentOnMissingId(): void
    {
        $handler = $this->makeHandler();
        $payload = new DeleteArticlePayload();
        $payload->setId('art_does_not_exist');

        $resource = $handler->handle($payload, new ArticleDeletedResource());

        self::assertSame(204, $resource->getStatusCode());
        self::assertSame([], $resource->getRenderContext());
    }

    public function testIdempotentDoubleDelete(): void
    {
        $handler = $this->makeHandler();
        $payload = new DeleteArticlePayload();
        $payload->setId('art_00002');

        $first = $handler->handle($payload, new ArticleDeletedResource());
        $second = $handler->handle($payload, new ArticleDeletedResource());

        self::assertSame(204, $first->getStatusCode());
        self::assertSame(204, $second->getStatusCode(), 'second DELETE on the same id is still 204');
    }

    private function makeHandler(): DeleteArticleHandler
    {
        $handler = new DeleteArticleHandler();
        $this->inject($handler, 'repository', $this->repository);
        return $handler;
    }
}
