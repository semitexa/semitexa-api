<?php

declare(strict_types=1);

namespace Semitexa\Api\Tests\Fixtures\Demo\Domain;

use DateTimeImmutable;

/**
 * Demo article model used by the semitexa-api showcase endpoints.
 *
 * Immutable. Mutations return new instances so the in-memory repository can
 * version state without callers holding stale references.
 */
final readonly class Article
{
    public function __construct(
        public string $id,
        public string $title,
        public string $body,
        public bool $published,
        public DateTimeImmutable $createdAt,
        public DateTimeImmutable $updatedAt,
    ) {}

    /**
     * Apply a partial change set. Unspecified fields are preserved.
     * The caller controls $now so tests stay deterministic.
     *
     * @param array{title?: string, body?: string, published?: bool} $changes
     */
    public function withChanges(array $changes, DateTimeImmutable $now): self
    {
        return new self(
            id: $this->id,
            title: $changes['title'] ?? $this->title,
            body: $changes['body'] ?? $this->body,
            published: $changes['published'] ?? $this->published,
            createdAt: $this->createdAt,
            updatedAt: $now,
        );
    }

    /**
     * Plain-array projection for JSON responses.
     *
     * @return array{
     *     id: string, title: string, body: string, published: bool,
     *     createdAt: string, updatedAt: string,
     * }
     */
    public function toArray(): array
    {
        return [
            'id'        => $this->id,
            'title'     => $this->title,
            'body'      => $this->body,
            'published' => $this->published,
            'createdAt' => $this->createdAt->format(\DateTimeInterface::ATOM),
            'updatedAt' => $this->updatedAt->format(\DateTimeInterface::ATOM),
        ];
    }
}
