<?php

declare(strict_types=1);

namespace Semitexa\Api\Tests\Fixtures\Demo\Showcase\Handler;

use Semitexa\Api\Tests\Fixtures\Demo\Showcase\Payload\ShowcasePagePayload;
use Semitexa\Api\Tests\Fixtures\Demo\Showcase\Resource\ShowcasePageResource;
use Semitexa\Core\Attribute\AsPayloadHandler;
use Semitexa\Core\Contract\TypedHandlerInterface;

#[AsPayloadHandler(payload: ShowcasePagePayload::class, resource: ShowcasePageResource::class)]
final class ShowcasePageHandler implements TypedHandlerInterface
{
    public function handle(ShowcasePagePayload $payload, ShowcasePageResource $resource): ShowcasePageResource
    {
        $html = file_get_contents(__DIR__ . '/../View/showcase.html');
        if ($html === false) {
            $html = '<h1>Showcase template missing</h1>';
        }
        return $resource->withHtml($html);
    }
}
