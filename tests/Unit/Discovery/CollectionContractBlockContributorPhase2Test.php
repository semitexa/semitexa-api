<?php

declare(strict_types=1);

namespace Semitexa\Api\Tests\Unit\Discovery;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Semitexa\Api\Discovery\CollectionContractBlockContributor;
use Semitexa\Api\Tests\Fixtures\Collection\AutoModeCollectionResponse;
use Semitexa\Api\Tests\Fixtures\Collection\BadFilterOptionsCollectionResponse;
use Semitexa\Api\Tests\Fixtures\Collection\PageModeCollectionResponse;
use Semitexa\Api\Tests\Fixtures\Collection\PaginatedOnlyCollectionResponse;
use Semitexa\Api\Tests\Fixtures\Collection\UndeclaredPolicyCollectionResponse;
use Semitexa\Core\Resource\Pagination\CollectionPageRequest;

/**
 * One Way Phase 2: the contributor's projection of the new
 * declarations — search/modes/per-route bounds/filterOptions — plus
 * the two compatibility guarantees: undeclared routes keep the exact
 * Phase 1 pagination keys, and `#[CollectionPaginated]` alone now
 * earns the block (the relaxed emission rule).
 */
final class CollectionContractBlockContributorPhase2Test extends TestCase
{
    private function blocksFor(string $responseClass): array
    {
        return (new CollectionContractBlockContributor())
            ->contributeBlocks('Payload\\Irrelevant', $responseClass);
    }

    #[Test]
    public function full_phase2_stanza_projects_search_modes_bounds_and_filter_options(): void
    {
        $collection = $this->blocksFor(AutoModeCollectionResponse::class)['collection'];

        self::assertSame(
            [
                'modes'          => ['page', 'cursor', 'auto'],
                'defaultPage'    => 1,
                'defaultPerPage' => 5,
                'maxPerPage'     => 25,
                'perPageOptions' => [5, 10, 25],
                'countThreshold' => 10,
            ],
            $collection['pagination'],
        );
        self::assertSame(['param' => 'q', 'fields' => ['name']], $collection['search']);
        self::assertSame(['fields' => ['name']], $collection['filterOptions']);
        self::assertSame(['name', 'id'], $collection['sort']['fields']);
    }

    #[Test]
    public function paginated_alone_earns_the_block(): void
    {
        $blocks = $this->blocksFor(PaginatedOnlyCollectionResponse::class);

        self::assertArrayHasKey('collection', $blocks);
        $pagination = $blocks['collection']['pagination'];
        self::assertSame(['page'], $pagination['modes']);
        self::assertSame(7, $pagination['defaultPerPage']);
        self::assertSame(30, $pagination['maxPerPage']);
        self::assertArrayNotHasKey('perPageOptions', $pagination);
        self::assertArrayNotHasKey('countThreshold', $pagination);
        self::assertArrayNotHasKey('search', $blocks['collection']);
        self::assertArrayNotHasKey('filterOptions', $blocks['collection']);
    }

    #[Test]
    public function pinned_modes_advertise_themselves_alone(): void
    {
        $pagination = $this->blocksFor(PageModeCollectionResponse::class)['collection']['pagination'];

        self::assertSame(['page'], $pagination['modes']);
    }

    #[Test]
    public function undeclared_policy_keeps_the_exact_phase1_pagination_keys(): void
    {
        $pagination = $this->blocksFor(UndeclaredPolicyCollectionResponse::class)['collection']['pagination'];

        self::assertSame(
            [
                'defaultPage'    => CollectionPageRequest::DEFAULT_PAGE,
                'defaultPerPage' => CollectionPageRequest::DEFAULT_PER_PAGE,
                'maxPerPage'     => CollectionPageRequest::MAX_PER_PAGE,
            ],
            $pagination,
        );
    }

    #[Test]
    public function filter_options_outside_the_filter_allowlist_fail_loudly(): void
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessageMatches('/not in its #\[CollectionFilterable\] allowlist/');
        $this->blocksFor(BadFilterOptionsCollectionResponse::class);
    }
}
