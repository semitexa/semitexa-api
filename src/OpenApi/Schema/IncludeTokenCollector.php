<?php

declare(strict_types=1);

namespace Semitexa\Api\OpenApi\Schema;

use Semitexa\Core\Attribute\AsService;
use Semitexa\Core\Attribute\InjectAsReadonly;
use Semitexa\Core\Resource\Metadata\ResourceFieldMetadata;
use Semitexa\Core\Resource\Metadata\ResourceMetadataRegistry;
use Semitexa\Core\Resource\Metadata\ResourceObjectMetadata;

/**
 * Phase 3c: collect the public `?include=` tokens an endpoint accepts, derived
 * from `ResourceObjectMetadata`. Pure function — no IO, no runtime renderer,
 * no DTO instantiation, no Request, no DB / ORM / HTTP.
 *
 * Rules (closed for v1):
 *   - Only fields where `isRelation()` AND `expandable === true` qualify.
 *   - Scalar / embedded-only fields are skipped.
 *   - Tokens are deterministic: alphabetical ASCII sort.
 *   - Nested tokens (`addresses.country`) are emitted iff the target Resource
 *     has at least one expandable relation. Depth is capped at 1 to keep the
 *     surface predictable; deeper nesting is deferred to a future phase.
 *   - Polymorphic union targets are walked one level via the first registered
 *     target — same conservative rule as `IncludeValidator`.
 */
#[AsService]
final class IncludeTokenCollector
{
    /** Maximum nesting depth supported in v1. */
    public const MAX_DEPTH = 1;

    #[InjectAsReadonly]
    protected ResourceMetadataRegistry $registry;

    /** Bypass property injection for unit tests. */
    public static function forTesting(ResourceMetadataRegistry $registry): self
    {
        $c = new self();
        $c->registry = $registry;
        return $c;
    }

    /**
     * @return list<string> sorted, deduped include tokens for the given root resource
     */
    public function collect(ResourceObjectMetadata $rootMetadata): array
    {
        $tokens = [];

        foreach ($rootMetadata->fields as $field) {
            if (!$this->isExpandableRelation($field)) {
                continue;
            }
            $token = $this->tokenFor($field);
            if ($token === null) {
                continue;
            }
            $tokens[$token] = true;

            // Depth-1 nesting.
            foreach ($this->nestedTokensFor($field) as $nested) {
                $tokens[$token . '.' . $nested] = true;
            }
        }

        $list = array_keys($tokens);
        sort($list, SORT_STRING);
        return $list;
    }

    private function isExpandableRelation(ResourceFieldMetadata $field): bool
    {
        return $field->isRelation() && $field->expandable;
    }

    private function tokenFor(ResourceFieldMetadata $field): ?string
    {
        if ($field->include === null || $field->include === '') {
            return null;
        }
        return $field->include;
    }

    /**
     * @return list<string> nested tokens emitted under this relation (depth=1)
     */
    private function nestedTokensFor(ResourceFieldMetadata $field): array
    {
        $target = $this->resolveTarget($field);
        if ($target === null) {
            return [];
        }

        $nested = [];
        foreach ($target->fields as $childField) {
            if (!$this->isExpandableRelation($childField)) {
                continue;
            }
            $token = $this->tokenFor($childField);
            if ($token !== null) {
                $nested[$token] = true;
            }
        }

        $list = array_keys($nested);
        sort($list, SORT_STRING);
        return $list;
    }

    private function resolveTarget(ResourceFieldMetadata $field): ?ResourceObjectMetadata
    {
        if ($field->target !== null) {
            return $this->registry->get($field->target);
        }
        // Polymorphic union — walk the first registered target conservatively.
        if ($field->unionTargets !== null && $field->unionTargets !== []) {
            return $this->registry->get($field->unionTargets[0]);
        }
        return null;
    }
}
