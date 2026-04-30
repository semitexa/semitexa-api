<?php

declare(strict_types=1);

namespace Semitexa\Api\Tests\Fixtures\Demo\Showcase\Resource;

use Semitexa\Core\Http\Response\ResourceResponse;

/**
 * Plain HTML response for the api-showcase page.
 *
 * Stays a ResourceResponse rather than an HtmlResponse so it does not pull in
 * the SSR asset pipeline / SEO machinery — the page is self-contained.
 */
final class ShowcasePageResource extends ResourceResponse
{
    public function withHtml(string $html): self
    {
        $this->setContent($html);
        $this->setHeader('Content-Type', 'text/html; charset=utf-8');
        return $this;
    }
}
