<?php

declare(strict_types=1);

namespace Semitexa\Api\Tests\Unit\OpenApi;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Phase 3a/3b runtime safety: schema generation must be a pure function over
 * the metadata graph. Static source check: none of the OpenAPI files imports
 * a runtime renderer / IriBuilder / Request / DB / ORM / HTTP client.
 */
final class OpenApiIsolationTest extends TestCase
{
    #[Test]
    public function openapi_files_have_no_io_or_runtime_render_imports(): void
    {
        $sources = [
            __DIR__ . '/../../../src/OpenApi/Schema/ResourceSchemaGenerator.php',
            __DIR__ . '/../../../src/OpenApi/Schema/IncludeTokenCollector.php',
            __DIR__ . '/../../../src/OpenApi/Route/ResourceRouteSchemaGenerator.php',
            __DIR__ . '/../../../src/OpenApi/OpenApiDocumentBuilder.php',
            __DIR__ . '/../../../src/Application/Console/Command/DumpOpenApiCommand.php',
        ];

        $forbidden = [
            'use PDO',
            'Doctrine\\',
            'Semitexa\\Orm\\',
            'GuzzleHttp\\',
            'Psr\\Http\\Client\\',
            'JsonResourceRenderer',     // schema generation must not invoke the renderer
            'IriBuilder',                // schema generation must not need request URLs
            // Note: Request import is allowed in lifecycle/wiring layers, but not in schema gen.
            'use Semitexa\\Core\\Request;',
        ];

        foreach ($sources as $path) {
            $content = file_get_contents($path);
            self::assertNotFalse($content, "Cannot read $path");
            foreach ($forbidden as $needle) {
                self::assertStringNotContainsString(
                    $needle,
                    $content,
                    sprintf('Phase 3a/3b file %s must not import %s', basename($path), $needle),
                );
            }
        }
    }
}
