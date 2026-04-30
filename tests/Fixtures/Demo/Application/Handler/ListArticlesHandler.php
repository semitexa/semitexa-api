<?php

declare(strict_types=1);

namespace Semitexa\Api\Tests\Fixtures\Demo\Application\Handler;

use Semitexa\Api\Tests\Fixtures\Demo\Application\Payload\ListArticlesPayload;
use Semitexa\Api\Tests\Fixtures\Demo\Application\Resource\ArticleCollectionResource;
use Semitexa\Api\Tests\Fixtures\Demo\Domain\Article;
use Semitexa\Api\Tests\Fixtures\Demo\Domain\InMemoryArticleRepository;
use Semitexa\Core\Attribute\AsPayloadHandler;
use Semitexa\Core\Attribute\InjectAsReadonly;
use Semitexa\Core\Contract\TypedHandlerInterface;
use Semitexa\Core\Exception\ValidationException;

#[AsPayloadHandler(payload: ListArticlesPayload::class, resource: ArticleCollectionResource::class)]
final class ListArticlesHandler implements TypedHandlerInterface
{
    #[InjectAsReadonly]
    protected InMemoryArticleRepository $repository;

    public function handle(ListArticlesPayload $payload, ArticleCollectionResource $resource): ArticleCollectionResource
    {
        $filter = $payload->getFilter();
        $allowed = ['', 'published', 'drafts'];
        if (!in_array($filter, $allowed, true)) {
            throw new ValidationException(['filter' => ['filter must be one of: published, drafts']]);
        }

        $articles = $this->repository->all();
        $filtered = match ($filter) {
            'published' => array_values(array_filter($articles, static fn (Article $a): bool => $a->published)),
            'drafts'    => array_values(array_filter($articles, static fn (Article $a): bool => !$a->published)),
            default     => $articles,
        };

        $publishedCount = count(array_filter($articles, static fn (Article $a): bool => $a->published));

        return $resource
            ->withArticles($filtered)
            ->withMeta([
                'total'     => count($articles),
                'published' => $publishedCount,
                'filter'    => $filter === '' ? null : $filter,
            ]);
    }
}
