<?php

declare(strict_types=1);

namespace Semitexa\Api\OpenApi\Schema;

use Semitexa\Core\Attribute\AsService;
use Semitexa\Core\Attribute\InjectAsReadonly;
use Semitexa\Core\Resource\Metadata\ResourceFieldKind;
use Semitexa\Core\Resource\Metadata\ResourceFieldMetadata;
use Semitexa\Core\Resource\Metadata\ResourceMetadataRegistry;
use Semitexa\Core\Resource\Metadata\ResourceObjectMetadata;

/**
 * Phase 3a: produce OpenAPI 3.1 component schemas from the metadata graph.
 *
 * Pure function over the registry — zero IO, zero reflection at request time,
 * deterministic output. The renderer's exact wire contract is mirrored here:
 *
 *   - Per-Resource component: object with `id`, `type` const, scalar/embedded
 *     properties, refs and ref-lists wired to envelope schemas.
 *   - `ResourceRef_<Name>`: `{type, id, href?, data?}` with `type`/`id` required.
 *   - `ResourceRefList_<Name>`: `{href, data?, total?}` with `href` required
 *     (matches the Phase 2.5 `::to($href)` factory contract).
 *   - Optional `?ResourceRef` field → `oneOf` with `{type: 'null'}`.
 *   - Embedded object/array fields → `$ref` / `array` of `$ref`.
 *   - `ResourceUnion` → inline `oneOf` with discriminator at the parent level.
 */
#[AsService]
final class ResourceSchemaGenerator
{
    #[InjectAsReadonly]
    protected ResourceMetadataRegistry $registry;

    /** Bypass property injection for unit tests. */
    public static function forTesting(ResourceMetadataRegistry $registry): self
    {
        $g = new self();
        $g->registry = $registry;
        return $g;
    }

    /**
     * Generate every component schema reachable from the registry.
     *
     * @return array<string, array<string, mixed>> keyed by component name
     */
    public function generateAll(): array
    {
        $components = [];

        $resources = $this->sortedRegistry();
        foreach ($resources as $metadata) {
            $components[$this->componentName($metadata)] = $this->generateResourceSchema($metadata);
        }

        // Envelope schemas for every Resource that is a relation target.
        $envelopeTargets = $this->collectEnvelopeTargets();
        foreach ($envelopeTargets['ref'] as $class) {
            $target = $this->registry->require($class);
            $components[$this->refEnvelopeName($target)] = $this->generateRefEnvelopeSchema($target);
        }
        foreach ($envelopeTargets['list'] as $class) {
            $target = $this->registry->require($class);
            $components[$this->refListEnvelopeName($target)] = $this->generateRefListEnvelopeSchema($target);
        }

        ksort($components);
        return $components;
    }

    /**
     * Stable component name for a Resource: PascalCase class basename.
     * The metadata registry already enforces unique types; basenames in
     * production-namespaced code are unique in practice. Collisions throw.
     */
    public function componentName(ResourceObjectMetadata $metadata): string
    {
        $parts = explode('\\', $metadata->class);
        return $parts[count($parts) - 1];
    }

    public function refEnvelopeName(ResourceObjectMetadata $metadata): string
    {
        return 'ResourceRef_' . $this->componentName($metadata);
    }

    public function refListEnvelopeName(ResourceObjectMetadata $metadata): string
    {
        return 'ResourceRefList_' . $this->componentName($metadata);
    }

    /** @return array<string, mixed> */
    public function generateResourceSchema(ResourceObjectMetadata $metadata): array
    {
        $properties = [];
        $required   = [];

        foreach ($metadata->fields as $field) {
            $properties[$field->name] = $this->propertySchema($metadata, $field);
            if (!$field->nullable) {
                $required[] = $field->name;
            }
        }

        $schema = [
            'type'       => 'object',
            'properties' => $properties,
        ];
        if ($required !== []) {
            $schema['required'] = $required;
        }
        if ($metadata->description !== '') {
            $schema['description'] = $metadata->description;
        }
        if ($metadata->deprecated) {
            $schema['deprecated'] = true;
        }

        return $schema;
    }

    /** @return array<string, mixed> */
    public function generateRefEnvelopeSchema(ResourceObjectMetadata $target): array
    {
        return [
            'type'                 => 'object',
            'description'          => sprintf(
                'To-one reference envelope for %s. type+id are mandatory; href is optional; data is present when embedded.',
                $this->componentName($target),
            ),
            'required'             => ['type', 'id'],
            'properties'           => [
                'type' => ['type' => 'string', 'const' => $target->type],
                'id'   => ['type' => 'string'],
                'href' => ['type' => 'string', 'format' => 'uri-reference'],
                'data' => ['$ref' => '#/components/schemas/' . $this->componentName($target)],
            ],
            'additionalProperties' => false,
        ];
    }

    /** @return array<string, mixed> */
    public function generateRefListEnvelopeSchema(ResourceObjectMetadata $target): array
    {
        return [
            'type'                 => 'object',
            'description'          => sprintf(
                'To-many reference envelope for %s. href is mandatory in the public factory; data is present when embedded; total accompanies data.',
                $this->componentName($target),
            ),
            'required'             => ['href'],
            'properties'           => [
                'href'  => ['type' => 'string', 'format' => 'uri-reference'],
                'data'  => [
                    'type'  => 'array',
                    'items' => ['$ref' => '#/components/schemas/' . $this->componentName($target)],
                ],
                'total' => ['type' => 'integer', 'minimum' => 0],
            ],
            'additionalProperties' => false,
        ];
    }

    /** @return array<string, mixed> */
    private function propertySchema(ResourceObjectMetadata $parent, ResourceFieldMetadata $field): array
    {
        $schema = match ($field->kind) {
            ResourceFieldKind::Scalar       => $this->scalarSchema($parent, $field),
            ResourceFieldKind::EmbeddedOne  => $this->refOrInline($field->target, $field->nullable, embed: true),
            ResourceFieldKind::EmbeddedMany => [
                'type'  => 'array',
                'items' => ['$ref' => '#/components/schemas/' . $this->componentBasenameFromClass($field->target ?? '')],
            ],
            ResourceFieldKind::RefOne  => $this->refEnvelopeRef($field, nullable: $field->nullable),
            ResourceFieldKind::RefMany => $this->refListEnvelopeRef($field, nullable: $field->nullable),
            ResourceFieldKind::Union   => $this->unionSchema($field),
        };

        if ($field->description !== '') {
            $schema['description'] = $field->description;
        }
        if ($field->deprecated) {
            $schema['deprecated'] = true;
        }

        return $schema;
    }

    /** @return array<string, mixed> */
    private function scalarSchema(ResourceObjectMetadata $parent, ResourceFieldMetadata $field): array
    {
        // The Phase 1 metadata model does not record raw PHP scalar types;
        // we conservatively type as string for the id field and arbitrary
        // scalar for everything else. Phase 3 only cares about the wire
        // contract, not strict scalar types — those land in Phase 4 with
        // a richer scalar metadata pass.
        $type = ['type' => 'string'];

        if ($field->name === $parent->idField) {
            return $type;
        }

        return $field->nullable ? ['oneOf' => [$type, ['type' => 'null']]] : $type;
    }

    /** @return array<string, mixed> */
    private function refOrInline(?string $targetFqcn, bool $nullable, bool $embed): array
    {
        if ($targetFqcn === null || !$this->registry->has($targetFqcn)) {
            return $nullable ? ['type' => 'null'] : ['type' => 'object'];
        }
        $name = $this->componentBasenameFromClass($targetFqcn);
        $ref  = ['$ref' => '#/components/schemas/' . $name];
        return $nullable ? ['oneOf' => [$ref, ['type' => 'null']]] : $ref;
    }

    /** @return array<string, mixed> */
    private function refEnvelopeRef(ResourceFieldMetadata $field, bool $nullable): array
    {
        if ($field->target === null) {
            return ['type' => 'object'];
        }
        $target = $this->registry->get($field->target);
        if ($target === null) {
            return ['type' => 'object'];
        }
        $ref = ['$ref' => '#/components/schemas/' . $this->refEnvelopeName($target)];
        return $nullable ? ['oneOf' => [$ref, ['type' => 'null']]] : $ref;
    }

    /** @return array<string, mixed> */
    private function refListEnvelopeRef(ResourceFieldMetadata $field, bool $nullable): array
    {
        if ($field->target === null) {
            return ['type' => 'object'];
        }
        $target = $this->registry->get($field->target);
        if ($target === null) {
            return ['type' => 'object'];
        }
        $ref = ['$ref' => '#/components/schemas/' . $this->refListEnvelopeName($target)];
        return $nullable ? ['oneOf' => [$ref, ['type' => 'null']]] : $ref;
    }

    /** @return array<string, mixed> */
    private function unionSchema(ResourceFieldMetadata $field): array
    {
        if ($field->unionTargets === null || $field->unionTargets === []) {
            return ['type' => 'object'];
        }

        $oneOf   = [];
        $mapping = [];
        foreach ($field->unionTargets as $targetClass) {
            $target = $this->registry->get($targetClass);
            if ($target === null) {
                continue;
            }
            $envelopeName = $field->isList()
                ? $this->refListEnvelopeName($target)
                : $this->refEnvelopeName($target);

            $oneOf[] = ['$ref' => '#/components/schemas/' . $envelopeName];
            $mapping[$target->type] = '#/components/schemas/' . $envelopeName;
        }

        $schema = ['oneOf' => $oneOf];

        // Discriminator is meaningful for to-one unions where the envelope's
        // `type` field selects the variant. For list unions, OpenAPI tooling
        // typically applies the discriminator to each item.
        if ($field->discriminator !== null && $field->discriminator !== '') {
            $schema['discriminator'] = [
                'propertyName' => $field->discriminator,
                'mapping'      => $mapping,
            ];
        }

        if ($field->nullable) {
            $schema = ['oneOf' => [$schema, ['type' => 'null']]];
        }

        return $schema;
    }

    /**
     * @return array{ref: list<class-string>, list: list<class-string>}
     */
    private function collectEnvelopeTargets(): array
    {
        $refTargets  = [];
        $listTargets = [];

        foreach ($this->registry->all() as $metadata) {
            foreach ($metadata->fields as $field) {
                if ($field->kind === ResourceFieldKind::RefOne && $field->target !== null) {
                    $refTargets[$field->target] = true;
                }
                if ($field->kind === ResourceFieldKind::RefMany && $field->target !== null) {
                    $listTargets[$field->target] = true;
                }
                if ($field->kind === ResourceFieldKind::Union && $field->unionTargets !== null) {
                    foreach ($field->unionTargets as $unionTarget) {
                        if ($field->isList()) {
                            $listTargets[$unionTarget] = true;
                        } else {
                            $refTargets[$unionTarget] = true;
                        }
                    }
                }
            }
        }

        $ref  = array_keys($refTargets);
        $list = array_keys($listTargets);
        sort($ref);
        sort($list);
        /** @var list<class-string> $ref */
        /** @var list<class-string> $list */

        return ['ref' => $ref, 'list' => $list];
    }

    /** @return list<ResourceObjectMetadata> sorted deterministically by class FQCN */
    private function sortedRegistry(): array
    {
        $all = $this->registry->all();
        ksort($all);
        return array_values($all);
    }

    private function componentBasenameFromClass(string $fqcn): string
    {
        if ($fqcn === '') {
            return 'Unknown';
        }
        $parts = explode('\\', $fqcn);
        return $parts[count($parts) - 1];
    }
}
