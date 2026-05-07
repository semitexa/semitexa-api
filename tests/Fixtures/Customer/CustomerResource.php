<?php

declare(strict_types=1);

namespace Semitexa\Api\Tests\Fixtures\Customer;

use Semitexa\Core\Resource\Attribute\ResolveWith;
use Semitexa\Core\Resource\Attribute\ResourceField;
use Semitexa\Core\Resource\Attribute\ResourceId;
use Semitexa\Core\Resource\Attribute\ResourceObject;
use Semitexa\Core\Resource\Attribute\ResourceRef as ResourceRefAttr;
use Semitexa\Core\Resource\Attribute\ResourceRefList as ResourceRefListAttr;
use Semitexa\Core\Resource\ResourceObjectInterface;
use Semitexa\Core\Resource\ResourceRef;
use Semitexa\Core\Resource\ResourceRefList;

#[ResourceObject(type: 'customer')]
final readonly class CustomerResource implements ResourceObjectInterface
{
    public function __construct(
        #[ResourceId]
        public string $id,

        #[ResourceField(description: 'Display name of the customer.')]
        public string $name,

        #[ResourceRefAttr(
            target: ProfileResource::class,
            expandable: true,
            include: 'profile',
            href: '/customers/{id}/profile',
        )]
        #[ResolveWith(CustomerProfileResolver::class)]
        public ?ResourceRef $profile,

        #[ResourceRefListAttr(
            target: AddressResource::class,
            expandable: true,
            include: 'addresses',
            href: '/customers/{id}/addresses',
        )]
        #[ResolveWith(CustomerAddressesResolver::class)]
        public ResourceRefList $addresses,
    ) {
    }
}
