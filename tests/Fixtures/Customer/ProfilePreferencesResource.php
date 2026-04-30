<?php

declare(strict_types=1);

namespace Semitexa\Api\Tests\Fixtures\Customer;

use Semitexa\Core\Resource\Attribute\ResourceField;
use Semitexa\Core\Resource\Attribute\ResourceId;
use Semitexa\Core\Resource\Attribute\ResourceObject;
use Semitexa\Core\Resource\ResourceObjectInterface;

#[ResourceObject(type: 'profile_preferences')]
final readonly class ProfilePreferencesResource implements ResourceObjectInterface
{
    public function __construct(
        #[ResourceId]
        public string $id,
        #[ResourceField(description: 'UI theme preference (e.g. light/dark/auto).')]
        public string $theme,
        #[ResourceField(description: 'IETF BCP 47 language tag preferred by the user.')]
        public string $locale,
    ) {
    }
}
