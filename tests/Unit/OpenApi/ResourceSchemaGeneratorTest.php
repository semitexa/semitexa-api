<?php

declare(strict_types=1);

namespace Semitexa\Api\Tests\Unit\OpenApi;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Semitexa\Api\OpenApi\Schema\ResourceSchemaGenerator;
use Semitexa\Core\Resource\Metadata\ResourceMetadataExtractor;
use Semitexa\Core\Resource\Metadata\ResourceMetadataRegistry;
use Semitexa\Core\Tests\Unit\Resource\Fixtures\AddressResource;
use Semitexa\Core\Tests\Unit\Resource\Fixtures\BotResource;
use Semitexa\Core\Tests\Unit\Resource\Fixtures\CommentResource;
use Semitexa\Core\Tests\Unit\Resource\Fixtures\CustomerResource;
use Semitexa\Core\Tests\Unit\Resource\Fixtures\PreferencesResource;
use Semitexa\Core\Tests\Unit\Resource\Fixtures\ProfileResource;
use Semitexa\Core\Tests\Unit\Resource\Fixtures\UserResource;

final class ResourceSchemaGeneratorTest extends TestCase
{
    private function customerRegistry(): ResourceMetadataRegistry
    {
        $extractor = new ResourceMetadataExtractor();
        $registry  = ResourceMetadataRegistry::forTesting($extractor);
        $registry->register($extractor->extract(AddressResource::class));
        $registry->register($extractor->extract(PreferencesResource::class));
        $registry->register($extractor->extract(ProfileResource::class));
        $registry->register($extractor->extract(CustomerResource::class));
        return $registry;
    }

    private function unionRegistry(): ResourceMetadataRegistry
    {
        $extractor = new ResourceMetadataExtractor();
        $registry  = ResourceMetadataRegistry::forTesting($extractor);
        $registry->register($extractor->extract(UserResource::class));
        $registry->register($extractor->extract(BotResource::class));
        $registry->register($extractor->extract(CommentResource::class));
        return $registry;
    }

    #[Test]
    public function generates_basic_resource_schema_with_required_props(): void
    {
        $g = ResourceSchemaGenerator::forTesting($this->customerRegistry());
        $components = $g->generateAll();

        self::assertArrayHasKey('AddressResource', $components);
        self::assertSame('object', $components['AddressResource']['type']);
        self::assertSame(['id', 'city', 'line1'], $components['AddressResource']['required']);
        self::assertSame('string', $components['AddressResource']['properties']['city']['type']);
    }

    #[Test]
    public function generates_ref_envelope_schema_with_type_and_id_required(): void
    {
        $g = ResourceSchemaGenerator::forTesting($this->customerRegistry());
        $components = $g->generateAll();

        self::assertArrayHasKey('ResourceRef_ProfileResource', $components);
        $env = $components['ResourceRef_ProfileResource'];
        self::assertSame(['type', 'id'], $env['required']);
        self::assertSame('profile', $env['properties']['type']['const']);
        self::assertSame(['$ref' => '#/components/schemas/ProfileResource'], $env['properties']['data']);
        self::assertFalse($env['additionalProperties']);
    }

    #[Test]
    public function generates_ref_list_envelope_schema_with_href_required(): void
    {
        $g = ResourceSchemaGenerator::forTesting($this->customerRegistry());
        $components = $g->generateAll();

        self::assertArrayHasKey('ResourceRefList_AddressResource', $components);
        $env = $components['ResourceRefList_AddressResource'];
        self::assertSame(['href'], $env['required']);
        self::assertSame('uri-reference', $env['properties']['href']['format']);
        self::assertSame(
            ['$ref' => '#/components/schemas/AddressResource'],
            $env['properties']['data']['items'],
        );
        self::assertSame(0, $env['properties']['total']['minimum']);
    }

    #[Test]
    public function nullable_optional_to_one_uses_oneOf_with_null(): void
    {
        $g = ResourceSchemaGenerator::forTesting($this->customerRegistry());
        $components = $g->generateAll();

        $profileField = $components['CustomerResource']['properties']['profile'];
        self::assertArrayHasKey('oneOf', $profileField);
        self::assertSame(['$ref' => '#/components/schemas/ResourceRef_ProfileResource'], $profileField['oneOf'][0]);
        self::assertSame(['type' => 'null'], $profileField['oneOf'][1]);

        // And `profile` is NOT in the `required` list of CustomerResource.
        self::assertNotContains('profile', $components['CustomerResource']['required']);
    }

    #[Test]
    public function required_to_many_relation_is_in_required_list(): void
    {
        $g = ResourceSchemaGenerator::forTesting($this->customerRegistry());
        $components = $g->generateAll();

        self::assertContains('addresses', $components['CustomerResource']['required']);
        self::assertSame(
            ['$ref' => '#/components/schemas/ResourceRefList_AddressResource'],
            $components['CustomerResource']['properties']['addresses'],
        );
    }

    #[Test]
    public function union_field_renders_inline_oneOf_with_discriminator(): void
    {
        $g = ResourceSchemaGenerator::forTesting($this->unionRegistry());
        $components = $g->generateAll();

        $author = $components['CommentResource']['properties']['author'];
        self::assertArrayHasKey('oneOf', $author);
        self::assertCount(2, $author['oneOf']);
        self::assertSame(['$ref' => '#/components/schemas/ResourceRef_UserResource'], $author['oneOf'][0]);
        self::assertSame(['$ref' => '#/components/schemas/ResourceRef_BotResource'], $author['oneOf'][1]);
        self::assertSame('type', $author['discriminator']['propertyName']);
        self::assertSame('#/components/schemas/ResourceRef_UserResource', $author['discriminator']['mapping']['user']);
        self::assertSame('#/components/schemas/ResourceRef_BotResource', $author['discriminator']['mapping']['bot']);
    }

    #[Test]
    public function union_list_uses_ref_list_envelope_per_target(): void
    {
        $g = ResourceSchemaGenerator::forTesting($this->unionRegistry());
        $components = $g->generateAll();

        $mentions = $components['CommentResource']['properties']['mentions'];
        self::assertSame(
            ['$ref' => '#/components/schemas/ResourceRefList_UserResource'],
            $mentions['oneOf'][0],
        );
        self::assertSame(
            ['$ref' => '#/components/schemas/ResourceRefList_BotResource'],
            $mentions['oneOf'][1],
        );
    }

    #[Test]
    public function component_naming_uses_class_basename_pascal_case(): void
    {
        $registry  = $this->customerRegistry();
        $g         = ResourceSchemaGenerator::forTesting($registry);

        self::assertSame('AddressResource', $g->componentName($registry->require(AddressResource::class)));
        self::assertSame('CustomerResource', $g->componentName($registry->require(CustomerResource::class)));
        self::assertSame('ResourceRef_ProfileResource', $g->refEnvelopeName($registry->require(ProfileResource::class)));
        self::assertSame('ResourceRefList_AddressResource', $g->refListEnvelopeName($registry->require(AddressResource::class)));
    }

    #[Test]
    public function generated_components_are_sorted_for_deterministic_output(): void
    {
        $g = ResourceSchemaGenerator::forTesting($this->customerRegistry());
        $first  = array_keys($g->generateAll());
        $second = array_keys($g->generateAll());

        self::assertSame($first, $second);
        $sorted = $first;
        sort($sorted);
        self::assertSame($sorted, $first, 'Component names must be sorted.');
    }

    #[Test]
    public function generation_does_not_mutate_the_registry(): void
    {
        $registry = $this->customerRegistry();
        $g = ResourceSchemaGenerator::forTesting($registry);

        $hashBefore = md5(serialize($registry->all()));
        $g->generateAll();
        $g->generateAll();
        self::assertSame($hashBefore, md5(serialize($registry->all())));
    }

    #[Test]
    public function no_duplicate_component_names_for_independent_targets(): void
    {
        $g = ResourceSchemaGenerator::forTesting($this->customerRegistry());
        $names = array_keys($g->generateAll());
        self::assertSame(array_unique($names), $names, 'Component names must be unique.');
    }
}
