<?php

declare(strict_types=1);

namespace Semitexa\Api\Tests\Unit\Discovery;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Semitexa\Api\Attribute\ProducesResourceCollection;
use Semitexa\Api\Discovery\CollectionContractBlockContributor;
use Semitexa\Api\Tests\Fixtures\Customer\CustomerCollectionJsonResponse;
use Semitexa\Api\Tests\Fixtures\Customer\CustomerJsonResponse;
use Semitexa\Api\Tests\Fixtures\Customer\CustomerResource;
use Semitexa\Api\Tests\Fixtures\Customer\ListCustomersPayload;
use Semitexa\Core\Resource\Pagination\CollectionPageRequest;

/**
 * One Way Pattern — Phase 1: the `collection` block is a pure projection of
 * the EXISTING phase-6 allowlist attributes plus the static
 * CollectionPageRequest bounds. No allowlist attributes → no block (the
 * design's degradation rule); non-collection responses → no block.
 */
final class CollectionContractBlockContributorTest extends TestCase
{
    private CollectionContractBlockContributor $contributor;

    protected function setUp(): void
    {
        $this->contributor = new CollectionContractBlockContributor();
    }

    #[Test]
    public function collection_response_with_allowlists_contributes_the_block(): void
    {
        $blocks = $this->contributor->contributeBlocks(
            ListCustomersPayload::class,
            CustomerCollectionJsonResponse::class,
        );

        self::assertSame(
            [
                'collection' => [
                    'pagination' => [
                        'defaultPage'    => CollectionPageRequest::DEFAULT_PAGE,
                        'defaultPerPage' => CollectionPageRequest::DEFAULT_PER_PAGE,
                        'maxPerPage'     => CollectionPageRequest::MAX_PER_PAGE,
                    ],
                    'sort'   => ['fields' => ['id', 'name']],
                    'filter' => ['fields' => [
                        'id'   => ['eq', 'in'],
                        'name' => ['eq', 'contains'],
                    ]],
                ],
            ],
            $blocks,
        );
    }

    #[Test]
    public function singular_response_contributes_nothing(): void
    {
        self::assertSame(
            [],
            $this->contributor->contributeBlocks(ListCustomersPayload::class, CustomerJsonResponse::class),
        );
    }

    #[Test]
    public function collection_response_without_allowlists_contributes_nothing(): void
    {
        self::assertSame(
            [],
            $this->contributor->contributeBlocks(ListCustomersPayload::class, BareCollectionResponseFixture::class),
        );
    }

    #[Test]
    public function null_or_unknown_response_contributes_nothing(): void
    {
        self::assertSame([], $this->contributor->contributeBlocks(ListCustomersPayload::class, null));
        /** @phpstan-ignore-next-line intentionally bogus class-string */
        self::assertSame([], $this->contributor->contributeBlocks(ListCustomersPayload::class, 'Acme\\Nope'));
    }

    #[Test]
    public function resolves_resource_class_from_both_produces_attributes(): void
    {
        self::assertSame(
            CustomerResource::class,
            $this->contributor->resolveResourceClass(CustomerCollectionJsonResponse::class),
        );
        self::assertSame(
            CustomerResource::class,
            $this->contributor->resolveResourceClass(CustomerJsonResponse::class),
        );
        self::assertNull($this->contributor->resolveResourceClass(null));
        self::assertNull($this->contributor->resolveResourceClass(self::class));
    }
}

/** Collection response with no allowlist attributes — degradation fixture. */
#[ProducesResourceCollection(CustomerResource::class)]
final class BareCollectionResponseFixture
{
}
