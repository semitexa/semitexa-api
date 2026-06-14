<?php

declare(strict_types=1);

namespace Semitexa\Api\Tests\Unit\Attribute;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Semitexa\Api\Attribute\CollectionFilterOptions;
use Semitexa\Api\Attribute\CollectionPaginated;
use Semitexa\Api\Attribute\CollectionSearchable;
use Semitexa\Core\Resource\CollectionPaginationPolicy;

/**
 * One Way Phase 2: declaration-time validation of the new collection
 * attributes — a misdeclared route must fail at first resolution, not
 * serve a wrong contract.
 */
final class CollectionPhase2AttributesTest extends TestCase
{
    #[Test]
    public function searchable_defaults_to_the_grid_proven_param_q(): void
    {
        $attr = new CollectionSearchable(fields: ['label', 'body']);

        self::assertSame('q', $attr->param);
        self::assertSame(['label', 'body'], $attr->fields);
    }

    #[Test]
    public function searchable_rejects_an_empty_field_list(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new CollectionSearchable(fields: []);
    }

    #[Test]
    public function searchable_rejects_nested_fields(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new CollectionSearchable(fields: ['category.name']);
    }

    #[Test]
    public function searchable_rejects_an_empty_param(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new CollectionSearchable(fields: ['label'], param: ' ');
    }

    #[Test]
    public function paginated_resolves_to_the_core_policy(): void
    {
        $policy = (new CollectionPaginated(
            mode: 'auto',
            defaultPerPage: 5,
            perPageOptions: [5, 10],
            maxPerPage: 25,
            countThreshold: 10,
        ))->toPolicy();

        self::assertSame(CollectionPaginationPolicy::MODE_AUTO, $policy->mode);
        self::assertSame(5, $policy->defaultPerPage);
        self::assertSame([5, 10], $policy->perPageOptions);
        self::assertSame(25, $policy->maxPerPage);
        self::assertSame(10, $policy->countThreshold);
        self::assertTrue($policy->declared);
    }

    #[Test]
    public function paginated_rejects_an_unknown_mode_at_declaration(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new CollectionPaginated(mode: 'windowed');
    }

    #[Test]
    public function paginated_rejects_a_default_above_max_at_declaration(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new CollectionPaginated(defaultPerPage: 30, maxPerPage: 25);
    }

    #[Test]
    public function filter_options_require_at_least_one_field(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new CollectionFilterOptions([]);
    }

    #[Test]
    public function filter_options_reject_blank_field_names(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new CollectionFilterOptions(['']);
    }
}
