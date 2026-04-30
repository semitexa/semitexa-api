<?php

declare(strict_types=1);

namespace Semitexa\Api\Tests\Fixtures\Demo\Application\Resource;

use Semitexa\Api\Tests\Fixtures\Demo\Http\JsonResource;
use Semitexa\Core\Http\HttpStatus;

/**
 * 204 No Content response for DELETE.
 *
 * The render context is intentionally empty: ResponseRenderer returns the
 * resource untouched when no render handle and no context are set, so the
 * eventual HttpResponse carries an empty body.
 */
final class ArticleDeletedResource extends JsonResource
{
    public function noContent(): self
    {
        $this->setStatusCode(HttpStatus::NoContent->value);
        return $this;
    }
}
