<?php

declare(strict_types=1);

namespace Semitexa\Api\Tests\Fixtures\Demo\Domain;

use Semitexa\Core\Attribute\AsService;
use Semitexa\Core\Attribute\InjectAsReadonly;

/**
 * DEMO ONLY — NOT FOR PRODUCTION.
 *
 * Worker-scoped in-memory store for the /api/v1/demo/articles showcase. State
 * lives in static properties: persists for the life of a Swoole worker, resets
 * on restart, and is NOT shared across workers, processes, or hosts. Data is
 * lost on every redeploy. There is no concurrency control, no transactions,
 * no replication, and no auth-aware filtering.
 *
 * Deliberately not ORM-backed so the showcase runs without a database and a
 * fresh clone can hit /api-showcase immediately. For real persistence, plug
 * an ORM-backed repository into the same contract.
 */
#[AsService]
final class InMemoryArticleRepository
{
    /** @var array<string, Article>|null */
    private static ?array $articles = null;
    private static int $sequence = 0;

    #[InjectAsReadonly]
    protected DemoClock $clock;

    /** @return list<Article> */
    public function all(): array
    {
        $this->ensureSeeded();
        return array_values(self::$articles ?? []);
    }

    public function find(string $id): ?Article
    {
        $this->ensureSeeded();
        return self::$articles[$id] ?? null;
    }

    public function save(Article $article): void
    {
        $this->ensureSeeded();
        self::$articles[$article->id] = $article;
    }

    public function delete(string $id): bool
    {
        $this->ensureSeeded();
        if (!isset(self::$articles[$id])) {
            return false;
        }
        unset(self::$articles[$id]);
        return true;
    }

    public function nextId(): string
    {
        $this->ensureSeeded();
        self::$sequence++;
        return sprintf('art_%05d', self::$sequence);
    }

    /** Reset to the seed dataset. Used by tests and the demo "reset" affordance. */
    public function reset(): void
    {
        self::$articles = null;
        self::$sequence = 0;
        $this->ensureSeeded();
    }

    private function ensureSeeded(): void
    {
        if (self::$articles !== null) {
            return;
        }

        $clock = $this->clock ?? new DemoClock();
        $now = $clock->now();
        $earlier = $now->modify('-2 hours');
        $earliest = $now->modify('-1 day');

        self::$articles = [];
        foreach ([
            new Article('art_00001', 'Welcome to Semitexa API',
                'This article is served by an in-memory store inside the semitexa-api package. '
                . 'GET /api/v1/demo/articles/art_00001 returns this body.',
                true, $earliest, $earliest),
            new Article('art_00002', 'Attribute-driven routing',
                'Each endpoint is a Payload DTO + Handler + Resource. The #[ExternalApi] '
                . 'attribute opts the route into the machine error envelope and version headers.',
                true, $earlier, $earlier),
            new Article('art_00003', 'Draft: format negotiation',
                'JSON-LD and content negotiation are tracked as a follow-up epic. This article '
                . 'is unpublished so the GET collection demo can show filtering.',
                false, $now, $now),
        ] as $a) {
            self::$articles[$a->id] = $a;
        }
        self::$sequence = 3;
    }
}
