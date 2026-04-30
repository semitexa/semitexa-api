<?php

declare(strict_types=1);

namespace Semitexa\Api\Tests\Fixtures\Demo\Application\Handler;

use Semitexa\Api\Tests\Fixtures\Demo\Application\Payload\ReplaceArticlePayload;
use Semitexa\Api\Tests\Fixtures\Demo\Application\Resource\ArticleResource;
use Semitexa\Api\Tests\Fixtures\Demo\Domain\DemoClock;
use Semitexa\Api\Tests\Fixtures\Demo\Domain\InMemoryArticleRepository;
use Semitexa\Core\Attribute\AsPayloadHandler;
use Semitexa\Core\Attribute\InjectAsReadonly;
use Semitexa\Core\Contract\TypedHandlerInterface;
use Semitexa\Core\Exception\NotFoundException;
use Semitexa\Core\Exception\ValidationException;

#[AsPayloadHandler(payload: ReplaceArticlePayload::class, resource: ArticleResource::class)]
final class ReplaceArticleHandler implements TypedHandlerInterface
{
    #[InjectAsReadonly]
    protected InMemoryArticleRepository $repository;

    #[InjectAsReadonly]
    protected DemoClock $clock;

    public function handle(ReplaceArticlePayload $payload, ArticleResource $resource): ArticleResource
    {
        $errors = [];
        if ($payload->getTitle() === '') {
            $errors['title'][] = 'title is required';
        }
        if ($payload->getBody() === '') {
            $errors['body'][] = 'body is required for full replacement (use PATCH for partial updates)';
        }
        if ($errors !== []) {
            throw new ValidationException($errors);
        }

        $existing = $this->repository->find($payload->getId());
        if ($existing === null) {
            throw new NotFoundException('Article', $payload->getId());
        }

        $replaced = $existing->withChanges(
            [
                'title'     => $payload->getTitle(),
                'body'      => $payload->getBody(),
                'published' => $payload->isPublished(),
            ],
            $this->clock->now(),
        );
        $this->repository->save($replaced);

        return $resource->withArticle($replaced);
    }
}
