<?php

declare(strict_types=1);

namespace Semitexa\Api\Tests\Fixtures\Demo\Application\Payload;

use Semitexa\Api\Attribute\ApiVersion;
use Semitexa\Api\Attribute\ExternalApi;
use Semitexa\Api\Tests\Fixtures\Demo\Application\Resource\ArticleDeletedResource;
use Semitexa\Authorization\Attribute\PublicEndpoint;
use Semitexa\Core\Attribute\AsPayload;
use Semitexa\Core\Contract\ValidatablePayload;
use Semitexa\Core\Http\PayloadValidationResult;

/** DELETE /api/v1/demo/articles/{id}  → 204 No Content (idempotent: missing id is also 204) */
#[AsPayload(
    path: '/api/v1/demo/articles/{id}',
    methods: ['DELETE'],
    name: 'api.demo.articles.delete',
    responseWith: ArticleDeletedResource::class,
)]
#[ExternalApi(version: 'v1', description: 'Idempotent delete. Returns 204 whether or not the resource existed.')]
#[ApiVersion(version: '1.0.0')]
#[PublicEndpoint]
final class DeleteArticlePayload implements ValidatablePayload
{
    private string $id = '';

    public function getId(): string { return $this->id; }
    public function setId(string $v): void { $this->id = trim($v); }

    public function validate(): PayloadValidationResult
    {
        return new PayloadValidationResult(true, []);
    }
}
