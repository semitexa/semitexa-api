<?php

declare(strict_types=1);

namespace Semitexa\Api\Tests\Fixtures\Demo\Application\Handler;

use Semitexa\Api\Tests\Fixtures\Demo\Application\Payload\DeleteArticlePayload;
use Semitexa\Api\Tests\Fixtures\Demo\Application\Resource\ArticleDeletedResource;
use Semitexa\Api\Tests\Fixtures\Demo\Domain\InMemoryArticleRepository;
use Semitexa\Core\Attribute\AsPayloadHandler;
use Semitexa\Core\Attribute\InjectAsReadonly;
use Semitexa\Core\Contract\TypedHandlerInterface;

#[AsPayloadHandler(payload: DeleteArticlePayload::class, resource: ArticleDeletedResource::class)]
final class DeleteArticleHandler implements TypedHandlerInterface
{
    #[InjectAsReadonly]
    protected InMemoryArticleRepository $repository;

    public function handle(DeleteArticlePayload $payload, ArticleDeletedResource $resource): ArticleDeletedResource
    {
        // Idempotent: 204 whether or not the resource existed.
        $this->repository->delete($payload->getId());
        return $resource->noContent();
    }
}
