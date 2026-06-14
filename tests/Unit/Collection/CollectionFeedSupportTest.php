<?php

declare(strict_types=1);

namespace Semitexa\Api\Tests\Unit\Collection;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Semitexa\Api\Application\Service\Collection\CollectionFeedSupport;
use Semitexa\Api\Tests\Fixtures\Collection\AutoModeCollectionResponse;
use Semitexa\Api\Tests\Fixtures\Collection\CursorModeCollectionResponse;
use Semitexa\Api\Tests\Fixtures\Collection\PageModeCollectionResponse;
use Semitexa\Api\Tests\Fixtures\Collection\SingleModeCollectionResponse;
use Semitexa\Api\Tests\Fixtures\Collection\UndeclaredPolicyCollectionResponse;
use Semitexa\Core\Resource\Exception\InvalidCursorException;
use Semitexa\Core\Resource\Exception\InvalidFilterException;
use Semitexa\Core\Resource\Exception\InvalidPaginationException;
use Semitexa\Core\Resource\Exception\InvalidSortException;

/**
 * One Way Phase 2: the parse seam — raw params against the response
 * class's declarations → validated criteria with the declared bounds,
 * typed 400s for every deviation, and the per-mode request policing.
 */
final class CollectionFeedSupportTest extends TestCase
{
    private CollectionFeedSupport $support;

    protected function setUp(): void
    {
        $this->support = new CollectionFeedSupport();
    }

    #[Test]
    public function full_request_parses_into_validated_criteria_with_declared_bounds(): void
    {
        $criteria = $this->support->criteriaFor(
            AutoModeCollectionResponse::class,
            rawQ: '  acme  ',
            rawSort: '-name',
            rawFilter: 'name:contains:a',
            rawPage: '2',
            rawPerPage: '10',
        );

        self::assertSame('acme', $criteria->q);
        self::assertSame(['name'], $criteria->searchFields);
        self::assertSame('-name', $criteria->sort->toQueryString());
        self::assertSame('name:contains:a', $criteria->filter->toQueryString());
        self::assertSame(2, $criteria->page->page);
        self::assertSame(10, $criteria->page->perPage);
        self::assertTrue($criteria->pageWasRequested);
        self::assertSame('auto', $criteria->policy->mode);
        self::assertSame(10, $criteria->policy->countThreshold);
    }

    #[Test]
    public function omitted_per_page_takes_the_declared_default(): void
    {
        $criteria = $this->support->criteriaFor(AutoModeCollectionResponse::class);

        self::assertSame(5, $criteria->page->perPage);
        self::assertFalse($criteria->pageWasRequested);
        self::assertNull($criteria->q);
    }

    #[Test]
    public function per_page_above_the_declared_max_is_a_typed_400(): void
    {
        $this->expectException(InvalidPaginationException::class);
        $this->support->criteriaFor(AutoModeCollectionResponse::class, rawPerPage: '26');
    }

    #[Test]
    public function out_of_allowlist_sort_and_filter_raise_their_typed_400s(): void
    {
        try {
            $this->support->criteriaFor(AutoModeCollectionResponse::class, rawSort: 'bogus');
            self::fail('expected InvalidSortException');
        } catch (InvalidSortException) {
        }

        $this->expectException(InvalidFilterException::class);
        $this->support->criteriaFor(AutoModeCollectionResponse::class, rawFilter: 'name:gt:a');
    }

    #[Test]
    public function cursor_and_page_together_are_rejected(): void
    {
        $this->expectException(InvalidCursorException::class);
        $this->support->criteriaFor(AutoModeCollectionResponse::class, rawPage: '2', rawCursor: 'abc');
    }

    #[Test]
    public function page_mode_routes_reject_cursors(): void
    {
        $this->expectException(InvalidCursorException::class);
        $this->support->criteriaFor(PageModeCollectionResponse::class, rawCursor: 'abc');
    }

    #[Test]
    public function cursor_mode_routes_reject_explicit_pages(): void
    {
        $this->expectException(InvalidPaginationException::class);
        $this->support->criteriaFor(CursorModeCollectionResponse::class, rawPage: '2');
    }

    #[Test]
    public function single_mode_routes_reject_both_windowing_params(): void
    {
        try {
            $this->support->criteriaFor(SingleModeCollectionResponse::class, rawPage: '2');
            self::fail('expected InvalidPaginationException');
        } catch (InvalidPaginationException) {
        }

        $this->expectException(InvalidPaginationException::class);
        $this->support->criteriaFor(SingleModeCollectionResponse::class, rawCursor: 'abc');
    }

    #[Test]
    public function undeclared_routes_accept_both_modes_like_customers_today(): void
    {
        $pageCriteria = $this->support->criteriaFor(UndeclaredPolicyCollectionResponse::class, rawPage: '2');
        self::assertSame(2, $pageCriteria->page->page);
        self::assertFalse($pageCriteria->policy->declared);

        $cursorCriteria = $this->support->criteriaFor(UndeclaredPolicyCollectionResponse::class, rawCursor: 'abc');
        self::assertTrue($cursorCriteria->isCursorRequest());
    }

    #[Test]
    public function q_is_inert_when_the_route_declares_no_search(): void
    {
        $criteria = $this->support->criteriaFor(UndeclaredPolicyCollectionResponse::class, rawQ: 'acme');

        self::assertNull($criteria->q);
        self::assertSame([], $criteria->searchFields);
        self::assertFalse($criteria->hasSearch());
    }
}
