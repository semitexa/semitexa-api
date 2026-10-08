<?php

declare(strict_types=1);

namespace Semitexa\Api\Discovery;

use ReflectionClass;
use Semitexa\Api\Attribute\ProducesResourceCollection;
use Semitexa\Api\Attribute\ProducesResourceObject;
use Semitexa\Core\Attribute\AsService;
use Semitexa\Core\Attribute\SatisfiesServiceContract;
use Semitexa\Api\Application\Service\Collection\CollectionContractBlock;
use Semitexa\Api\Domain\Model\Collection\CollectionDeclarations;
use Semitexa\Core\Http\WatchScopesOf;
use Semitexa\Core\Contract\CollectionAwareContributorInterface;
use Semitexa\Core\Contract\RouteContractBlockContributorInterface;
use Semitexa\Core\Resource\Pagination\CollectionPageRequest;

/**
 * One Way Pattern — Phase 1+2: semitexa-api's `collection` block contributor.
 *
 * Projects the collection allowlist attributes on the response class —
 * `#[CollectionSortable]` / `#[CollectionFilterable]` (Phase 1) plus
 * `#[CollectionSearchable]` / `#[CollectionPaginated]` /
 * `#[CollectionFilterOptions]` (Phase 2) — into the route contract
 * document. Routes without `#[CollectionPaginated]` keep the static
 * {@see CollectionPageRequest} bounds, surfaced with EXACTLY the Phase 1
 * keys so their served documents stay byte-identical; a declared policy
 * adds `modes` / `perPageOptions` / `countThreshold` per-route.
 *
 * Degradation rule (design §1.1): a response class with none of the
 * collection attributes contributes nothing — the contract document then
 * equals today's OPTIONS shape plus the `input` block. The block is also
 * withheld for non-collection responses (no `#[ProducesResourceCollection]`):
 * the allowlists are collection vocabulary and would be dishonest on a
 * singular route.
 *
 * Doubles as the response→resource link resolver for the core assembler:
 * `#[ProducesResourceCollection]` / `#[ProducesResourceObject]` are api
 * vocabulary, and core must not read them itself.
 */
#[AsService]
#[SatisfiesServiceContract(of: RouteContractBlockContributorInterface::class)]
final class CollectionContractBlockContributor implements
    RouteContractBlockContributorInterface,
    CollectionAwareContributorInterface
{
    public function contributeBlocks(string $payloadClass, ?string $responseClass): array
    {
        if ($responseClass === null || !class_exists($responseClass)) {
            return [];
        }

        $ref = new ReflectionClass($responseClass);
        if ($ref->getAttributes(ProducesResourceCollection::class) === []) {
            return [];
        }

        $collection = CollectionContractBlock::build(
            CollectionDeclarations::fromResponseClass($responseClass),
            $this->watchScopesFor($payloadClass),
        );

        return $collection === null ? [] : ['collection' => $collection];
    }

    /**
     * The feed payload's declared `#[WatchScopes]` invalidation scope keys —
     * read from the PAYLOAD class (the route carrier), unlike the collection
     * allowlists which ride the response class: the watch list is a property
     * of the route's live serving, not of the envelope shape.
     *
     * @return list<string>
     */
    private function watchScopesFor(string $payloadClass): array
    {
        return WatchScopesOf::payload($payloadClass);
    }

    public function resolveResourceClass(?string $responseClass): ?string
    {
        if ($responseClass === null || !class_exists($responseClass)) {
            return null;
        }

        $ref = new ReflectionClass($responseClass);

        // Collection wins when both are present — same precedence as the
        // OpenAPI route generator.
        $collectionAttrs = $ref->getAttributes(ProducesResourceCollection::class);
        if ($collectionAttrs !== []) {
            /** @var ProducesResourceCollection $produces */
            $produces = $collectionAttrs[0]->newInstance();

            return class_exists($produces->resourceClass) ? $produces->resourceClass : null;
        }

        $objectAttrs = $ref->getAttributes(ProducesResourceObject::class);
        if ($objectAttrs !== []) {
            /** @var ProducesResourceObject $produces */
            $produces = $objectAttrs[0]->newInstance();

            return class_exists($produces->resourceClass) ? $produces->resourceClass : null;
        }

        return null;
    }

    /**
     * A response is a collection iff it declares `#[ProducesResourceCollection]`
     * — true even when it carries no sort/filter/pagination attributes (a bare
     * collection that contributes no `collection` block). This is the reliable
     * cardinality signal {@see RouteContract::$isCollection} surfaces.
     */
    public function resolvesCollection(?string $responseClass): bool
    {
        if ($responseClass === null || !class_exists($responseClass)) {
            return false;
        }

        return (new ReflectionClass($responseClass))->getAttributes(ProducesResourceCollection::class) !== [];
    }
}
