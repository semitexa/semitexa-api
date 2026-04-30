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

/** POST /api/v1/demo/articles  (JSON body) */
#[AsPayload(
    path: '/api/v1/demo/articles',
    methods: ['POST'],
    name: 'api.demo.articles.create',
    responseWith: ArticleResource::class,
    consumes: ['application/json'],
)]
#[ExternalApi(version: 'v1', description: 'Create a demo article. Empty title → 422 validation envelope.')]
#[ApiVersion(version: '1.0.0')]
#[PublicEndpoint]
final class CreateArticlePayload implements ValidatablePayload
{
    private string $title = '';
    private string $body = '';
    private bool $published = false;

    public function getTitle(): string { return $this->title; }
    public function setTitle(string $v): void { $this->title = trim($v); }

    public function getBody(): string { return $this->body; }
    public function setBody(string $v): void { $this->body = $v; }

    public function isPublished(): bool { return $this->published; }
    public function setPublished(bool $v): void { $this->published = $v; }

    /** Validation runs in the handler — see ListArticlesPayload for rationale. */
    public function validate(): PayloadValidationResult
    {
        return new PayloadValidationResult(true, []);
    }
}
