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

/**
 * PATCH /api/v1/demo/articles/{id}  (partial update)
 *
 * Distinguishes "not provided" from "set to empty/false" via *Provided flags
 * so the handler only updates fields the caller actually sent.
 */
#[AsPayload(
    path: '/api/v1/demo/articles/{id}',
    methods: ['PATCH'],
    name: 'api.demo.articles.patch',
    responseWith: ArticleResource::class,
    consumes: ['application/json'],
)]
#[ExternalApi(version: 'v1', description: 'Partial update. Only sent fields are applied.')]
#[ApiVersion(version: '1.0.0')]
#[PublicEndpoint]
final class PatchArticlePayload implements ValidatablePayload
{
    private string $id = '';
    private string $title = '';
    private string $body = '';
    private bool $published = false;

    private bool $titleProvided = false;
    private bool $bodyProvided = false;
    private bool $publishedProvided = false;

    public function getId(): string { return $this->id; }
    public function setId(string $v): void { $this->id = trim($v); }

    public function getTitle(): string { return $this->title; }
    public function setTitle(string $v): void { $this->title = trim($v); $this->titleProvided = true; }
    public function isTitleProvided(): bool { return $this->titleProvided; }

    public function getBody(): string { return $this->body; }
    public function setBody(string $v): void { $this->body = $v; $this->bodyProvided = true; }
    public function isBodyProvided(): bool { return $this->bodyProvided; }

    public function isPublished(): bool { return $this->published; }
    public function setPublished(bool $v): void { $this->published = $v; $this->publishedProvided = true; }
    public function isPublishedProvided(): bool { return $this->publishedProvided; }

    /** Validation runs in the handler — see ListArticlesPayload for rationale. */
    public function validate(): PayloadValidationResult
    {
        return new PayloadValidationResult(true, []);
    }
}
