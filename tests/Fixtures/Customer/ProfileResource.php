<?php

declare(strict_types=1);

namespace Semitexa\Api\Tests\Fixtures\Customer;

use Semitexa\Core\Resource\Attribute\ResolveWith;
use Semitexa\Core\Resource\Attribute\ResourceField;
use Semitexa\Core\Resource\Attribute\ResourceId;
use Semitexa\Core\Resource\Attribute\ResourceObject;
use Semitexa\Core\Resource\Attribute\ResourceRef as ResourceRefAttr;
use Semitexa\Core\Resource\ResourceObjectInterface;
use Semitexa\Core\Resource\ResourceRef;

#[ResourceObject(type: 'profile')]
final readonly class ProfileResource implements ResourceObjectInterface
{
    public function __construct(
        #[ResourceId]
        public string $id,
        #[ResourceField]
        public string $bio,

        #[ResourceRefAttr(
            target: ProfilePreferencesResource::class,
            expandable: true,
            include: 'preferences',
            href: '/customers/profiles/{id}/preferences',
        )]
        #[ResolveWith(ProfilePreferencesResolver::class)]
        public ?ResourceRef $preferences = null,
    ) {
    }
}
