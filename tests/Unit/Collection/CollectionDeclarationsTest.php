<?php

declare(strict_types=1);

namespace Semitexa\Api\Tests\Unit\Collection;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Semitexa\Api\Application\Service\Collection\CollectionContractBlock;
use Semitexa\Api\Attribute\CollectionFilterable;
use Semitexa\Api\Attribute\CollectionFilterOptions;
use Semitexa\Api\Attribute\CollectionPaginated;
use Semitexa\Api\Attribute\CollectionSearchable;
use Semitexa\Api\Attribute\CollectionSortable;
use Semitexa\Api\Domain\Model\Collection\CollectionDeclarations;
use Semitexa\Core\Resource\CollectionPaginationPolicy;

/**
 * tk-rs-feed: collection declarations are a value, read from #[Collection*]
 * attributes or built by a field-driven feed; the criteria parser and the
 * contract block read the value, never the attributes.
 */
final class CollectionDeclarationsTest extends TestCase
{
    #[Test]
    public function the_attributes_become_declarations_and_the_contract_block(): void
    {
        $declarations = CollectionDeclarations::fromResponseClass(DeclaredResponseFixture::class);

        self::assertSame(['title'], $declarations->sort);
        self::assertSame(['status' => ['eq']], $declarations->filter);
        self::assertSame(['status'], $declarations->filterOptions);
        self::assertSame(['title'], $declarations->searchable?->fields);
        self::assertSame(7, $declarations->policy->defaultPerPage);

        $block = CollectionContractBlock::build($declarations, ['scope']);
        self::assertSame(['page'], $block['pagination']['modes']);
        self::assertSame(['fields' => ['status']], $block['filterOptions']);
        self::assertSame(['scopes' => ['scope']], $block['live']);
    }

    #[Test]
    public function nothing_declared_is_nothing_to_describe(): void
    {
        $empty = new CollectionDeclarations(CollectionPaginationPolicy::default());
        self::assertTrue($empty->isEmpty());
        self::assertNull(CollectionContractBlock::build($empty));
        self::assertNotNull(CollectionContractBlock::build($empty, ['live-only']));
    }

    #[Test]
    public function options_for_a_field_that_is_not_filterable_are_refused(): void
    {
        $this->expectException(\LogicException::class);
        new CollectionDeclarations(CollectionPaginationPolicy::default(), filter: ['a' => ['eq']], filterOptions: ['b']);
    }
}

#[CollectionSortable(['title'])]
#[CollectionFilterable(['status' => ['eq']])]
#[CollectionFilterOptions(['status'])]
#[CollectionSearchable(fields: ['title'])]
#[CollectionPaginated(mode: 'page', defaultPerPage: 7, maxPerPage: 50)]
final class DeclaredResponseFixture
{
}
