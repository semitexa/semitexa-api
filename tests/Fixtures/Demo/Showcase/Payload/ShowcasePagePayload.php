<?php

declare(strict_types=1);

namespace Semitexa\Api\Tests\Fixtures\Demo\Showcase\Payload;

use Semitexa\Api\Tests\Fixtures\Demo\Showcase\Resource\ShowcasePageResource;
use Semitexa\Authorization\Attribute\PublicEndpoint;
use Semitexa\Core\Attribute\AsPayload;
use Semitexa\Core\Contract\ValidatablePayload;
use Semitexa\Core\Http\PayloadValidationResult;

/**
 * GET /api-showcase  → interactive HTML page exercising the /api/v1/demo/articles endpoints.
 *
 * Intentionally NOT decorated with #[ExternalApi] — this is a developer page,
 * not an API endpoint. Errors from this route therefore use Core's default
 * (HTML/plain) error mapping, not the machine envelope.
 */
#[AsPayload(
    path: '/api-showcase',
    methods: ['GET'],
    name: 'api.demo.showcase',
    responseWith: ShowcasePageResource::class,
)]
#[PublicEndpoint]
final class ShowcasePagePayload implements ValidatablePayload
{
    public function validate(): PayloadValidationResult
    {
        return new PayloadValidationResult(true, []);
    }
}
