<?php

declare(strict_types=1);

namespace Semitexa\Api\Tests\Fixtures\Demo\Application\Payload;

use Semitexa\Api\Attribute\ApiVersion;
use Semitexa\Api\Attribute\ExternalApi;
use Semitexa\Api\Tests\Fixtures\Demo\Application\Resource\ArticleCollectionResource;
use Semitexa\Authorization\Attribute\PublicEndpoint;
use Semitexa\Core\Attribute\AsPayload;
use Semitexa\Core\Contract\ValidatablePayload;
use Semitexa\Core\Http\PayloadValidationResult;

/**
 * GET /api/v1/demo/articles
 *
 * Optional ?filter=published|drafts to demonstrate query-string handling.
 */
#[AsPayload(
    path: '/api/v1/demo/articles',
    methods: ['GET'],
    name: 'api.demo.articles.list',
    responseWith: ArticleCollectionResource::class,
)]
#[ExternalApi(version: 'v1', description: 'List demo articles. Showcases collection responses.')]
#[ApiVersion(version: '1.0.0')]
#[PublicEndpoint]
final class ListArticlesPayload implements ValidatablePayload
{
    private string $filter = '';

    public function getFilter(): string { return $this->filter; }
    public function setFilter(string $v): void { $this->filter = trim($v); }

    /**
     * Validation lives in the handler so all error responses share one envelope.
     * The Core PayloadValidator short-circuits with a flat {errors:...} body that
     * bypasses ExternalApiExceptionMapper; throwing ValidationException from the
     * handler keeps every demo error shaped as the machine envelope.
     */
    public function validate(): PayloadValidationResult
    {
        return new PayloadValidationResult(true, []);
    }
}
