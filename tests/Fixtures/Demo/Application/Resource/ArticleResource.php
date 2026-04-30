<?php

declare(strict_types=1);

namespace Semitexa\Api\Tests\Fixtures\Demo\Application\Resource;

use Semitexa\Api\Tests\Fixtures\Demo\Domain\Article;
use Semitexa\Api\Tests\Fixtures\Demo\Http\JsonResource;
use Semitexa\Core\Http\HttpStatus;

/**
 * Single-article JSON response. Used by GET item, POST, PUT, PATCH handlers.
 *
 * Body shape: { "data": { id, title, body, published, createdAt, updatedAt } }
 */
final class ArticleResource extends JsonResource
{
    public function withArticle(Article $article): self
    {
        return $this->setField('data', $article->toArray());
    }

    public function created(): self
    {
        $this->setStatusCode(HttpStatus::Created->value);
        return $this;
    }
}
