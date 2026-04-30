<?php

declare(strict_types=1);

namespace Semitexa\Api\Tests\Fixtures\Demo\Http;

use Semitexa\Core\Http\Response\ResourceResponse;

/**
 * Base resource for JSON-only API endpoints in the semitexa-api demo.
 *
 * The Core ResponseRenderer JSON-encodes a ResourceResponse automatically when
 * no render handle is set and the render context is non-empty. This subclass
 * just sugars accumulation: typed subclasses expose with*() builders that all
 * delegate to setField($key, $value).
 *
 * Intentionally does NOT extend HtmlResponse — these endpoints have no Twig
 * template, no SEO, and no asset pipeline.
 */
class JsonResource extends ResourceResponse
{
    /**
     * Append one keyed value to the render context. Returns $this so subclass
     * builder methods can chain idiomatically.
     */
    protected function setField(string $key, mixed $value): static
    {
        $context = $this->getRenderContext();
        $context[$key] = $value;
        $this->setRenderContext($context);
        return $this;
    }
}
