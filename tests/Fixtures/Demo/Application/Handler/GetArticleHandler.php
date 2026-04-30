<?php

declare(strict_types=1);

namespace Semitexa\Api\Tests\Fixtures\Demo\Application\Handler;

use Semitexa\Api\Tests\Fixtures\Demo\Application\Payload\GetArticlePayload;
use Semitexa\Api\Tests\Fixtures\Demo\Application\Resource\ArticleResource;
use Semitexa\Api\Tests\Fixtures\Demo\Domain\InMemoryArticleRepository;
use Semitexa\Core\Attribute\AsPayloadHandler;
use Semitexa\Core\Attribute\InjectAsReadonly;
use Semitexa\Core\Contract\TypedHandlerInterface;
use Semitexa\Core\Exception\NotFoundException;

#[AsPayloadHandler(payload: GetArticlePayload::class, resource: ArticleResource::class)]
final class GetArticleHandler implements TypedHandlerInterface
{
    #[InjectAsReadonly]
    protected InMemoryArticleRepository $repository;

    public function handle(GetArticlePayload $payload, ArticleResource $resource): ArticleResource
    {
        $article = $this->repository->find($payload->getId());
        if ($article === null) {
            throw new NotFoundException('Article', $payload->getId());
        }
        return $resource->withArticle($article);
    }
}
