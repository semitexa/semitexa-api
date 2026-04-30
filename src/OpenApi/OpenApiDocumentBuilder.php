<?php

declare(strict_types=1);

namespace Semitexa\Api\OpenApi;

use Semitexa\Api\OpenApi\Route\ResourceRouteSchemaGenerator;
use Semitexa\Api\OpenApi\Schema\ResourceSchemaGenerator;
use Semitexa\Core\Attribute\AsService;
use Semitexa\Core\Attribute\InjectAsReadonly;

/**
 * Combines Phase 3a (component schemas) and Phase 3b (paths) into a single
 * OpenAPI 3.1 document. Output is deterministic.
 */
#[AsService]
final class OpenApiDocumentBuilder
{
    public const OPENAPI_VERSION = '3.1.0';

    #[InjectAsReadonly]
    protected ResourceSchemaGenerator $schemaGenerator;

    #[InjectAsReadonly]
    protected ResourceRouteSchemaGenerator $routeGenerator;

    public static function forTesting(
        ResourceSchemaGenerator $schemaGenerator,
        ResourceRouteSchemaGenerator $routeGenerator,
    ): self {
        $b = new self();
        $b->schemaGenerator = $schemaGenerator;
        $b->routeGenerator  = $routeGenerator;
        return $b;
    }

    /**
     * @return array<string, mixed>
     */
    public function build(string $title = 'Semitexa Resource API', string $version = '0.0.0'): array
    {
        $schemas = $this->schemaGenerator->generateAll();
        $paths   = $this->routeGenerator->generatePaths();

        $document = [
            'openapi' => self::OPENAPI_VERSION,
            'info'    => [
                'title'   => $title,
                'version' => $version,
            ],
            // Cast to object so empty maps encode as `{}` per OpenAPI 3.x
            // (paths and components.schemas are Map types, not arrays).
            'paths'      => $paths === [] ? (object) [] : $paths,
            'components' => [
                'schemas' => $schemas === [] ? (object) [] : $schemas,
            ],
        ];

        return $document;
    }

    /**
     * Encode the document to deterministic, pretty-printed JSON.
     */
    public function toJson(string $title = 'Semitexa Resource API', string $version = '0.0.0'): string
    {
        $json = json_encode(
            $this->build($title, $version),
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR,
        );
        return $json . "\n";
    }
}
