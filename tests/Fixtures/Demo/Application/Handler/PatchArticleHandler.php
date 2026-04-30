<?php

declare(strict_types=1);

namespace Semitexa\Api\Tests\Fixtures\Demo\Application\Handler;

use Semitexa\Api\Tests\Fixtures\Demo\Application\Payload\PatchArticlePayload;
use Semitexa\Api\Tests\Fixtures\Demo\Application\Resource\ArticleResource;
use Semitexa\Api\Tests\Fixtures\Demo\Domain\DemoClock;
use Semitexa\Api\Tests\Fixtures\Demo\Domain\InMemoryArticleRepository;
use Semitexa\Core\Attribute\AsPayloadHandler;
use Semitexa\Core\Attribute\InjectAsReadonly;
use Semitexa\Core\Contract\TypedHandlerInterface;
use Semitexa\Core\Exception\NotFoundException;
use Semitexa\Core\Exception\ValidationException;

#[AsPayloadHandler(payload: PatchArticlePayload::class, resource: ArticleResource::class)]
final class PatchArticleHandler implements TypedHandlerInterface
{
    #[InjectAsReadonly]
    protected InMemoryArticleRepository $repository;

    #[InjectAsReadonly]
    protected DemoClock $clock;

    public function handle(PatchArticlePayload $payload, ArticleResource $resource): ArticleResource
    {
        if (!$payload->isTitleProvided() && !$payload->isBodyProvided() && !$payload->isPublishedProvided()) {
            throw new ValidationException([
                '_' => ['PATCH must provide at least one of: title, body, published'],
            ]);
        }
        if ($payload->isTitleProvided() && $payload->getTitle() === '') {
            throw new ValidationException(['title' => ['title cannot be empty when provided']]);
        }

        $existing = $this->repository->find($payload->getId());
        if ($existing === null) {
            throw new NotFoundException('Article', $payload->getId());
        }

        $changes = [];
        if ($payload->isTitleProvided())     { $changes['title']     = $payload->getTitle(); }
        if ($payload->isBodyProvided())      { $changes['body']      = $payload->getBody(); }
        if ($payload->isPublishedProvided()) { $changes['published'] = $payload->isPublished(); }

        $patched = $existing->withChanges($changes, $this->clock->now());
        $this->repository->save($patched);

        return $resource->withArticle($patched);
    }
}
