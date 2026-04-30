<?php

declare(strict_types=1);

namespace Semitexa\Api\Tests\Demo\Showcase;

use PHPUnit\Framework\TestCase;
use Semitexa\Api\Tests\Fixtures\Demo\Showcase\Handler\ShowcasePageHandler;
use Semitexa\Api\Tests\Fixtures\Demo\Showcase\Payload\ShowcasePagePayload;
use Semitexa\Api\Tests\Fixtures\Demo\Showcase\Resource\ShowcasePageResource;

final class ShowcasePageHandlerTest extends TestCase
{
    public function testReturnsHtmlForAllSixOperations(): void
    {
        $handler = new ShowcasePageHandler();
        $resource = $handler->handle(new ShowcasePagePayload(), new ShowcasePageResource());

        $core = $resource->toCoreResponse();
        self::assertSame(200, $core->getStatusCode());
        self::assertSame('text/html; charset=utf-8', $core->getHeaders()['Content-Type']);

        $html = $core->getContent();
        self::assertStringContainsString('<title>Semitexa API', $html);

        // Each operation section must be present.
        foreach (['op-list', 'op-get', 'op-create', 'op-replace', 'op-patch', 'op-delete'] as $id) {
            self::assertStringContainsString('id="' . $id . '"', $html, "missing section #{$id}");
        }

        // Each method gets a labelled badge.
        foreach (['method GET', 'method POST', 'method PUT', 'method PATCH', 'method DELETE'] as $cls) {
            self::assertStringContainsString($cls, $html);
        }

        // The interactive runner is wired up.
        self::assertStringContainsString('button class="run"', $html);
        self::assertStringContainsString('await fetch(url, init)', $html);
    }
}
