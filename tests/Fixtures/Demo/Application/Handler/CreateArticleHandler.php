<?php

declare(strict_types=1);

namespace Semitexa\Api\Tests\Fixtures\Demo\Application\Handler;

use Semitexa\Api\Tests\Fixtures\Demo\Application\Payload\CreateArticlePayload;
use Semitexa\Api\Tests\Fixtures\Demo\Application\Resource\ArticleResource;
use Semitexa\Api\Tests\Fixtures\Demo\Domain\Article;
use Semitexa\Api\Tests\Fixtures\Demo\Domain\DemoClock;
use Semitexa\Api\Tests\Fixtures\Demo\Domain\InMemoryArticleRepository;
use Semitexa\Core\Attribute\AsPayloadHandler;
use Semitexa\Core\Attribute\InjectAsReadonly;
use Semitexa\Core\Contract\TypedHandlerInterface;
use Semitexa\Core\Exception\ValidationException;

#[AsPayloadHandler(payload: CreateArticlePayload::class, resource: ArticleResource::class)]
final class CreateArticleHandler implements TypedHandlerInterface
{
    #[InjectAsReadonly]
    protected InMemoryArticleRepository $repository;

    #[InjectAsReadonly]
    protected DemoClock $clock;

    public function handle(CreateArticlePayload $payload, ArticleResource $resource): ArticleResource
    {
        $errors = [];
        if ($payload->getTitle() === '') {
            $errors['title'][] = 'title is required';
        } elseif (mb_strlen($payload->getTitle()) > 200) {
            $errors['title'][] = 'title must be 200 characters or fewer';
        }
        if (mb_strlen($payload->getBody()) > 10000) {
            $errors['body'][] = 'body must be 10000 characters or fewer';
        }
        if ($errors !== []) {
            throw new ValidationException($errors);
        }

        $now = $this->clock->now();
        $article = new Article(
            id: $this->repository->nextId(),
            title: $payload->getTitle(),
            body: $payload->getBody(),
            published: $payload->isPublished(),
            createdAt: $now,
            updatedAt: $now,
        );
        $this->repository->save($article);

        return $resource->withArticle($article)->created();
    }
}
