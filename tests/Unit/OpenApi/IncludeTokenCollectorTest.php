<?php

declare(strict_types=1);

namespace Semitexa\Api\Tests\Unit\OpenApi;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Semitexa\Api\OpenApi\Schema\IncludeTokenCollector;
use Semitexa\Core\Resource\Attribute\ResourceField;
use Semitexa\Core\Resource\Attribute\ResourceId;
use Semitexa\Core\Resource\Attribute\ResourceObject;
use Semitexa\Core\Resource\Attribute\ResourceRef as ResourceRefAttr;
use Semitexa\Core\Resource\Attribute\ResourceRefList as ResourceRefListAttr;
use Semitexa\Core\Resource\Metadata\ResourceMetadataExtractor;
use Semitexa\Core\Resource\Metadata\ResourceMetadataRegistry;
use Semitexa\Core\Resource\ResourceObjectInterface;
use Semitexa\Core\Resource\ResourceRef;
use Semitexa\Core\Resource\ResourceRefList;
use Semitexa\Core\Tests\Unit\Resource\Fixtures\AddressResource;
use Semitexa\Core\Tests\Unit\Resource\Fixtures\BotResource;
use Semitexa\Core\Tests\Unit\Resource\Fixtures\CommentResource;
use Semitexa\Core\Tests\Unit\Resource\Fixtures\CustomerResource;
use Semitexa\Core\Tests\Unit\Resource\Fixtures\PreferencesResource;
use Semitexa\Core\Tests\Unit\Resource\Fixtures\ProfileResource;
use Semitexa\Core\Tests\Unit\Resource\Fixtures\UserResource;

#[ResourceObject(type: 'phase3c.country')]
final readonly class CountryResourceFixture3c implements ResourceObjectInterface
{
    public function __construct(
        #[ResourceId]
        public string $id,
        #[ResourceField]
        public string $name,
    ) {
    }
}

#[ResourceObject(type: 'phase3c.address')]
final readonly class AddressResourceFixture3c implements ResourceObjectInterface
{
    public function __construct(
        #[ResourceId]
        public string $id,
        #[ResourceField]
        public string $city,
        #[ResourceRefAttr(target: CountryResourceFixture3c::class, expandable: true, include: 'country', href: '/addresses/{id}/country')]
        public ?ResourceRef $country = null,
    ) {
    }
}

#[ResourceObject(type: 'phase3c.customer')]
final readonly class CustomerResourceFixture3c implements ResourceObjectInterface
{
    public function __construct(
        #[ResourceId]
        public string $id,
        #[ResourceField]
        public string $name,
        #[ResourceRefListAttr(target: AddressResourceFixture3c::class, expandable: true, include: 'addresses', href: '/customers/{id}/addresses')]
        public ResourceRefList $addresses,
    ) {
    }
}

#[ResourceObject(type: 'phase3c.scalars-only')]
final readonly class ScalarsOnlyResourceFixture3c implements ResourceObjectInterface
{
    public function __construct(
        #[ResourceId]
        public string $id,
        #[ResourceField]
        public string $name,
    ) {
    }
}

#[ResourceObject(type: 'phase3c.non-expandable')]
final readonly class NonExpandableRelationResourceFixture3c implements ResourceObjectInterface
{
    public function __construct(
        #[ResourceId]
        public string $id,
        // expandable: false (default) — not embeddable via ?include=, so no token.
        #[ResourceRefAttr(target: ProfileResource::class, include: 'profile', href: '/x/{id}/profile')]
        public ?ResourceRef $profile = null,
    ) {
    }
}

final class IncludeTokenCollectorTest extends TestCase
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

    #[Test]
    public function collects_top_level_expandable_relation_tokens_sorted(): void
    {
        $registry = $this->customerRegistry();
        $c = IncludeTokenCollector::forTesting($registry);
        $tokens = $c->collect($registry->require(CustomerResource::class));

        // Phase 6g: ProfileResource gained an optional resolver-backed
        // `preferences` relation. The collector emits the dotted
        // `profile.preferences` token alongside the top-level ones,
        // alphabetically sorted.
        self::assertSame(['addresses', 'profile', 'profile.preferences'], $tokens);
    }

    #[Test]
    public function skips_scalar_fields(): void
    {
        $extractor = new ResourceMetadataExtractor();
        $registry  = ResourceMetadataRegistry::forTesting($extractor);
        $registry->register($extractor->extract(ScalarsOnlyResourceFixture3c::class));

        $c = IncludeTokenCollector::forTesting($registry);
        $tokens = $c->collect($registry->require(ScalarsOnlyResourceFixture3c::class));

        self::assertSame([], $tokens);
    }

    #[Test]
    public function skips_non_expandable_relations(): void
    {
        $extractor = new ResourceMetadataExtractor();
        $registry  = ResourceMetadataRegistry::forTesting($extractor);
        $registry->register($extractor->extract(ProfileResource::class));
        $registry->register($extractor->extract(NonExpandableRelationResourceFixture3c::class));

        $c = IncludeTokenCollector::forTesting($registry);
        $tokens = $c->collect($registry->require(NonExpandableRelationResourceFixture3c::class));

        self::assertSame([], $tokens);
    }

    #[Test]
    public function emits_nested_tokens_when_target_has_expandable_relations(): void
    {
        $extractor = new ResourceMetadataExtractor();
        $registry  = ResourceMetadataRegistry::forTesting($extractor);
        $registry->register($extractor->extract(CountryResourceFixture3c::class));
        $registry->register($extractor->extract(AddressResourceFixture3c::class));
        $registry->register($extractor->extract(CustomerResourceFixture3c::class));

        $c = IncludeTokenCollector::forTesting($registry);
        $tokens = $c->collect($registry->require(CustomerResourceFixture3c::class));

        // Customer.addresses → Address has `country` (expandable) → nested token.
        self::assertSame(['addresses', 'addresses.country'], $tokens);
    }

    #[Test]
    public function depth_is_capped_at_one(): void
    {
        // The collector explicitly limits nesting to depth 1. Even if the
        // metadata graph is deeper, no `addresses.country.continent`-style
        // token should ever be emitted.
        self::assertSame(1, IncludeTokenCollector::MAX_DEPTH);
    }

    #[Test]
    public function union_relations_use_first_registered_target_for_nesting(): void
    {
        $extractor = new ResourceMetadataExtractor();
        $registry  = ResourceMetadataRegistry::forTesting($extractor);
        $registry->register($extractor->extract(UserResource::class));
        $registry->register($extractor->extract(BotResource::class));
        $registry->register($extractor->extract(CommentResource::class));

        $c = IncludeTokenCollector::forTesting($registry);
        $tokens = $c->collect($registry->require(CommentResource::class));

        // CommentResource: author (NOT expandable in the fixture) → skipped.
        // CommentResource: mentions (expandable: true) → token "mentions".
        // User has no expandable relations → no nested tokens.
        self::assertSame(['mentions'], $tokens);
    }

    #[Test]
    public function repeated_calls_are_deterministic(): void
    {
        $registry = $this->customerRegistry();
        $c = IncludeTokenCollector::forTesting($registry);

        $a = $c->collect($registry->require(CustomerResource::class));
        $b = $c->collect($registry->require(CustomerResource::class));
        self::assertSame($a, $b);
    }

    #[Test]
    public function does_not_mutate_registry(): void
    {
        $registry = $this->customerRegistry();
        $c = IncludeTokenCollector::forTesting($registry);

        $hashBefore = md5(serialize($registry->all()));
        $c->collect($registry->require(CustomerResource::class));
        $c->collect($registry->require(CustomerResource::class));
        self::assertSame($hashBefore, md5(serialize($registry->all())));
    }
}
