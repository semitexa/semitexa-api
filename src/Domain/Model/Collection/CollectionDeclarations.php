<?php

declare(strict_types=1);

namespace Semitexa\Api\Domain\Model\Collection;

use ReflectionClass;
use Semitexa\Api\Attribute\CollectionFilterable;
use Semitexa\Api\Attribute\CollectionFilterOptions;
use Semitexa\Api\Attribute\CollectionPaginated;
use Semitexa\Api\Attribute\CollectionSearchable;
use Semitexa\Api\Attribute\CollectionSortable;
use Semitexa\Core\Resource\CollectionPaginationPolicy;

/**
 * What a collection route allows — pagination, search, sort, filter and the
 * filters with server-fed options — whatever declared it. The criteria parser
 * (CollectionFeedSupport) and the OPTIONS contract (CollectionContractBlock)
 * both read this, so they cannot disagree.
 *
 * Two sources today: the `#[Collection*]` attributes on a response class
 * (fromResponseClass), and a field-driven feed definition (semitexa/crud)
 * that builds one directly.
 */
final readonly class CollectionDeclarations
{
    /**
     * @param list<string>                $sort          sortable fields
     * @param array<string, list<string>> $filter        filterable field → operators
     * @param list<string>                $filterOptions filterable fields whose options the server feeds
     */
    public function __construct(
        public CollectionPaginationPolicy $policy,
        public ?CollectionSearchable $searchable = null,
        public array $sort = [],
        public array $filter = [],
        public array $filterOptions = [],
    ) {
        foreach ($filterOptions as $field) {
            if (!array_key_exists($field, $filter)) {
                throw new \LogicException(sprintf('Filter options for "%s", which is not a filterable field.', $field));
            }
        }
    }

    /** True when nothing at all is declared (the route is not a collection to describe). */
    public function isEmpty(): bool
    {
        return !$this->policy->declared && $this->searchable === null && $this->sort === []
            && $this->filter === [] && $this->filterOptions === [];
    }

    /**
     * The declarations a response class carries as `#[Collection*]` attributes.
     *
     * @param class-string $responseClass
     */
    public static function fromResponseClass(string $responseClass): self
    {
        $ref = new ReflectionClass($responseClass);
        $first = static function (string $attribute) use ($ref): ?object {
            $attrs = $ref->getAttributes($attribute);

            return $attrs === [] ? null : $attrs[0]->newInstance();
        };

        /** @var CollectionPaginated|null $paginated */
        $paginated = $first(CollectionPaginated::class);
        /** @var CollectionSortable|null $sortable */
        $sortable = $first(CollectionSortable::class);
        /** @var CollectionFilterable|null $filterable */
        $filterable = $first(CollectionFilterable::class);
        /** @var CollectionFilterOptions|null $options */
        $options = $first(CollectionFilterOptions::class);
        /** @var CollectionSearchable|null $searchable */
        $searchable = $first(CollectionSearchable::class);
        $filter = $filterable?->fields ?? [];
        $optionFields = $options !== null ? array_values($options->fields) : [];
        foreach ($optionFields as $field) {
            if (!array_key_exists($field, $filter)) {
                throw new \LogicException(sprintf(
                    '%s declares #[CollectionFilterOptions] field "%s" that is not in its #[CollectionFilterable] allowlist.',
                    $responseClass,
                    $field,
                ));
            }
        }

        return new self(
            policy:        $paginated?->toPolicy() ?? CollectionPaginationPolicy::default(),
            searchable:    $searchable,
            sort:          $sortable !== null ? array_values($sortable->fields) : [],
            filter:        $filter,
            filterOptions: $optionFields,
        );
    }
}
