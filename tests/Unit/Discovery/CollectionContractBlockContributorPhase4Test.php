<?php

declare(strict_types=1);

namespace Semitexa\Api\Tests\Unit\Discovery;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Semitexa\Api\Discovery\CollectionContractBlockContributor;
use Semitexa\Api\Tests\Fixtures\Collection\PaginatedOnlyCollectionResponse;
use Semitexa\Core\Attribute\WatchScopes;

/**
 * One Way Phase 4: the contributor's projection of the payload-side
 * `#[WatchScopes]` declaration as `collection.live.scopes` — the
 * contract's "WHY is this feed live" block. The scopes ride the
 * PAYLOAD class (the route carrier), unlike the collection allowlists
 * which ride the response class.
 */
final class CollectionContractBlockContributorPhase4Test extends TestCase
{
    #[Test]
    public function watch_scopes_on_the_payload_project_as_collection_live_scopes(): void
    {
        $collection = (new CollectionContractBlockContributor())
            ->contributeBlocks(WatchScopedFeedPayloadFixture::class, PaginatedOnlyCollectionResponse::class)['collection'];

        self::assertSame(['scopes' => ['ui_playground_pings', 'playground_articles']], $collection['live']);
    }

    #[Test]
    public function a_payload_without_watch_scopes_contributes_no_live_block(): void
    {
        $collection = (new CollectionContractBlockContributor())
            ->contributeBlocks(UnscopedFeedPayloadFixture::class, PaginatedOnlyCollectionResponse::class)['collection'];

        self::assertArrayNotHasKey('live', $collection);
    }

    #[Test]
    public function an_unloadable_payload_class_contributes_no_live_block(): void
    {
        $collection = (new CollectionContractBlockContributor())
            ->contributeBlocks('Payload\\DoesNotExist', PaginatedOnlyCollectionResponse::class)['collection'];

        self::assertArrayNotHasKey('live', $collection);
    }
}

#[WatchScopes('ui_playground_pings', 'playground_articles')]
final class WatchScopedFeedPayloadFixture
{
}

final class UnscopedFeedPayloadFixture
{
}
