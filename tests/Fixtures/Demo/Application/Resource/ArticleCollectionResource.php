<?php

declare(strict_types=1);

namespace Semitexa\Api\Tests\Fixtures\Demo\Application\Resource;

use Semitexa\Api\Tests\Fixtures\Demo\Domain\Article;
use Semitexa\Api\Tests\Fixtures\Demo\Http\JsonResource;

/**
 * Collection JSON response.
 *
 * Body shape:
 * {
 *   "data": [ { article }, ... ],
 *   "meta": { "total": int, "published": int, "filter": ?string }
 * }
 */
final class ArticleCollectionResource extends JsonResource
{
    /**
     * @param list<Article> $articles
     */
    public function withArticles(array $articles): self
    {
        return $this->setField('data', array_map(static fn (Article $a): array => $a->toArray(), $articles));
    }

    /**
     * @param array{total:int, published:int, filter:?string} $meta
     */
    public function withMeta(array $meta): self
    {
        return $this->setField('meta', $meta);
    }
}
