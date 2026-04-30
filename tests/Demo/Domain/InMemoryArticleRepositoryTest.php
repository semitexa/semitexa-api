<?php

declare(strict_types=1);

namespace Semitexa\Api\Tests\Demo\Domain;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;
use Semitexa\Api\Tests\Fixtures\Demo\Domain\Article;
use Semitexa\Api\Tests\Fixtures\Demo\Domain\FixedDemoClock;
use Semitexa\Api\Tests\Fixtures\Demo\Domain\InMemoryArticleRepository;

final class InMemoryArticleRepositoryTest extends TestCase
{
    private InMemoryArticleRepository $repo;

    protected function setUp(): void
    {
        $this->repo = new InMemoryArticleRepository();
        (new ReflectionProperty($this->repo, 'clock'))
            ->setValue($this->repo, new FixedDemoClock(new DateTimeImmutable('2026-01-15T12:00:00Z')));
        $this->repo->reset();
    }

    public function testSeedsThreeArticlesOnFirstAccess(): void
    {
        $articles = $this->repo->all();

        self::assertCount(3, $articles);
        self::assertSame('art_00001', $articles[0]->id);
        self::assertSame('art_00003', $articles[2]->id);

        $publishedCount = count(array_filter($articles, static fn (Article $a) => $a->published));
        self::assertSame(2, $publishedCount, 'two seeded articles are published, one is a draft');
    }

    public function testFindReturnsExistingArticle(): void
    {
        $article = $this->repo->find('art_00002');

        self::assertNotNull($article);
        self::assertSame('Attribute-driven routing', $article->title);
    }

    public function testFindReturnsNullForUnknownId(): void
    {
        self::assertNull($this->repo->find('art_does_not_exist'));
    }

    public function testSavePersistsAndReplaces(): void
    {
        $clock = new FixedDemoClock(new DateTimeImmutable('2026-01-15T13:00:00Z'));
        $original = $this->repo->find('art_00001');
        self::assertNotNull($original);

        $updated = $original->withChanges(['title' => 'Edited title'], $clock->now());
        $this->repo->save($updated);

        $reloaded = $this->repo->find('art_00001');
        self::assertNotNull($reloaded);
        self::assertSame('Edited title', $reloaded->title);
        self::assertSame($original->createdAt->format('c'), $reloaded->createdAt->format('c'));
        self::assertNotSame($original->updatedAt->format('c'), $reloaded->updatedAt->format('c'));
    }

    public function testDeleteReturnsTrueWhenIdExisted(): void
    {
        self::assertTrue($this->repo->delete('art_00001'));
        self::assertNull($this->repo->find('art_00001'));
    }

    public function testDeleteReturnsFalseWhenIdMissing(): void
    {
        self::assertFalse($this->repo->delete('art_does_not_exist'));
    }

    public function testNextIdIsMonotonicallyIncreasing(): void
    {
        $first = $this->repo->nextId();
        $second = $this->repo->nextId();

        self::assertSame('art_00004', $first);
        self::assertSame('art_00005', $second);
    }

    public function testResetRestoresSeedDataset(): void
    {
        $this->repo->delete('art_00001');
        $this->repo->delete('art_00002');
        self::assertCount(1, $this->repo->all());

        $this->repo->reset();
        self::assertCount(3, $this->repo->all());
    }
}
