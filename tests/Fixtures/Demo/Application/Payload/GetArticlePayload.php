<?php

declare(strict_types=1);

namespace Semitexa\Api\Tests\Fixtures\Demo\Application\Payload;

use Semitexa\Api\Attribute\ApiVersion;
use Semitexa\Api\Attribute\ExternalApi;
use Semitexa\Api\Tests\Fixtures\Demo\Application\Resource\ArticleResource;
use Semitexa\Authorization\Attribute\PublicEndpoint;
use Semitexa\Core\Attribute\AsPayload;
use Semitexa\Core\Contract\ValidatablePayload;
use Semitexa\Core\Http\PayloadValidationResult;

/** GET /api/v1/demo/articles/{id} */
#[AsPayload(
    path: '/api/v1/demo/articles/{id}',
    methods: ['GET'],
    name: 'api.demo.articles.get',
    responseWith: ArticleResource::class,
)]
#[ExternalApi(version: 'v1', description: 'Fetch one demo article. NotFoundException → 404 envelope.')]
#[ApiVersion(version: '1.0.0')]
#[PublicEndpoint]
final class GetArticlePayload implements ValidatablePayload
{
    private string $id = '';

    public function getId(): string { return $this->id; }
    public function setId(string $v): void { $this->id = trim($v); }

    public function validate(): PayloadValidationResult
    {
        return new PayloadValidationResult(true, []);
    }
}
