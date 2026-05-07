<?php

declare(strict_types=1);

namespace Semitexa\Api\Tests\Unit\OpenApi;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Semitexa\Api\OpenApi\OpenApiDocumentBuilder;
use Semitexa\Api\OpenApi\Route\ResourceRouteSchemaGenerator;
use Semitexa\Api\OpenApi\Schema\IncludeTokenCollector;
use Semitexa\Api\OpenApi\Schema\ResourceSchemaGenerator;
use Semitexa\Core\Discovery\ClassDiscovery;
use Semitexa\Core\Resource\Metadata\ResourceMetadataExtractor;
use Semitexa\Core\Resource\Metadata\ResourceMetadataRegistry;
use Semitexa\Api\Tests\Fixtures\Customer\AddressResource;
use Semitexa\Api\Tests\Fixtures\Customer\CustomerResource;
use Semitexa\Api\Tests\Fixtures\Customer\GetCustomerPayload;
use Semitexa\Api\Tests\Fixtures\Customer\ListCustomersPayload;
use Semitexa\Api\Tests\Fixtures\Customer\ProfilePreferencesResource;
use Semitexa\Api\Tests\Fixtures\Customer\ProfileResource;

final class OpenApiDocumentBuilderTest extends TestCase
{
    private function builder(): OpenApiDocumentBuilder
    {
        $extractor = new ResourceMetadataExtractor();
        $registry  = ResourceMetadataRegistry::forTesting($extractor);
        $registry->register($extractor->extract(AddressResource::class));
        $registry->register($extractor->extract(ProfilePreferencesResource::class));
        $registry->register($extractor->extract(ProfileResource::class));
        $registry->register($extractor->extract(CustomerResource::class));

        $schemaGen = ResourceSchemaGenerator::forTesting($registry);
        $discovery = new InMemoryDiscovery([GetCustomerPayload::class, ListCustomersPayload::class]);
        $includeTokens = IncludeTokenCollector::forTesting($registry);
        $routeGen  = ResourceRouteSchemaGenerator::forTesting($discovery, $registry, $schemaGen, $includeTokens);

        return OpenApiDocumentBuilder::forTesting($schemaGen, $routeGen);
    }

    #[Test]
    public function output_matches_golden_customer_document(): void
    {
        $expected = file_get_contents(__DIR__ . '/golden/customer.openapi.json');
        self::assertNotFalse($expected);

        $actual   = $this->builder()->toJson('Customer API', '1.0.0');

        self::assertSame($expected, $actual, "OpenAPI dump must match the golden file. Regenerate via: bin/semitexa openapi:dump --output=packages/semitexa-api/tests/Unit/OpenApi/golden/customer.openapi.json");
    }

    #[Test]
    public function repeated_builds_produce_identical_output(): void
    {
        $b = $this->builder();
        self::assertSame($b->toJson(), $b->toJson());
        self::assertSame($b->toJson(), $b->toJson());
    }

    #[Test]
    public function document_has_required_top_level_keys(): void
    {
        $doc = $this->builder()->build();
        self::assertSame('3.1.0', $doc['openapi']);
        self::assertArrayHasKey('info', $doc);
        self::assertArrayHasKey('paths', $doc);
        self::assertArrayHasKey('components', $doc);
        self::assertArrayHasKey('schemas', $doc['components']);
    }

    #[Test]
    public function empty_registry_produces_empty_paths_and_schemas_as_objects(): void
    {
        $extractor = new ResourceMetadataExtractor();
        $registry  = ResourceMetadataRegistry::forTesting($extractor);

        $schemaGen = ResourceSchemaGenerator::forTesting($registry);
        $discovery = new InMemoryDiscovery([]);
        $includeTokens = IncludeTokenCollector::forTesting($registry);
        $routeGen  = ResourceRouteSchemaGenerator::forTesting($discovery, $registry, $schemaGen, $includeTokens);

        $b = OpenApiDocumentBuilder::forTesting($schemaGen, $routeGen);
        $json = $b->toJson();

        self::assertStringContainsString('"paths": {}', $json);
        self::assertStringContainsString('"schemas": {}', $json);
    }
}
