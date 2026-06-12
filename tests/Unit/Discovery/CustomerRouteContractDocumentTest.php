<?php

declare(strict_types=1);

namespace Semitexa\Api\Tests\Unit\Discovery;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Semitexa\Api\Discovery\CollectionContractBlockContributor;
use Semitexa\Api\Tests\Fixtures\Customer\AddressResource;
use Semitexa\Api\Tests\Fixtures\Customer\CustomerCollectionJsonResponse;
use Semitexa\Api\Tests\Fixtures\Customer\CustomerResource;
use Semitexa\Api\Tests\Fixtures\Customer\GetCustomerPayload;
use Semitexa\Api\Tests\Fixtures\Customer\ListCustomersPayload;
use Semitexa\Api\Tests\Fixtures\Customer\ProfilePreferencesResource;
use Semitexa\Api\Tests\Fixtures\Customer\ProfileResource;
use Semitexa\Core\Http\DefaultRouteContractAssembler;
use Semitexa\Core\Http\PayloadMetadataReflector;
use Semitexa\Core\Resource\Metadata\ResourceMetadataExtractor;
use Semitexa\Core\Resource\Metadata\ResourceMetadataRegistry;

/**
 * One Way Pattern — Phase 1, integration-style: the customers collection
 * vertical (mirrored by this package's Customer fixtures) assembled through
 * the REAL default assembler + the REAL collection contributor produces the
 * full extended OPTIONS document — input roles, collection facts, output
 * metadata — exactly as the live `/playground/customers` route serves it.
 */
final class CustomerRouteContractDocumentTest extends TestCase
{
    protected function setUp(): void
    {
        PayloadMetadataReflector::clearCache();
    }

    private function assembler(): DefaultRouteContractAssembler
    {
        $extractor = new ResourceMetadataExtractor();
        $registry  = ResourceMetadataRegistry::forTesting($extractor);
        $registry->register($extractor->extract(AddressResource::class));
        $registry->register($extractor->extract(ProfilePreferencesResource::class));
        $registry->register($extractor->extract(ProfileResource::class));
        $registry->register($extractor->extract(CustomerResource::class));

        return DefaultRouteContractAssembler::forTesting(
            $registry,
            [new CollectionContractBlockContributor()],
        );
    }

    #[Test]
    public function list_customers_document_carries_all_three_blocks(): void
    {
        $document = $this->assembler()
            ->assemble(ListCustomersPayload::class, CustomerCollectionJsonResponse::class)
            ->toDocument();

        // Legacy base keys, unchanged.
        self::assertSame('/customers', $document['endpoint']);
        self::assertSame(['GET'], $document['methods']);
        self::assertSame('protected', $document['access'], 'the api fixture route is protected (live customers is public)');
        self::assertSame(['plain'], $document['modes']);

        // Input block: every reflected field, `query` roled as selection
        // (GraphQL render profile → field selection, NOT search).
        $byName = [];
        foreach ($document['input']['fields'] as $field) {
            $byName[$field['name']] = $field;
        }
        self::assertSame('selection', $byName['query']['role']);
        self::assertArrayNotHasKey('role', $byName['include']);

        // Collection block: the phase-6 allowlists + honest static bounds.
        self::assertSame(['id', 'name'], $document['collection']['sort']['fields']);
        self::assertSame(
            ['id' => ['eq', 'in'], 'name' => ['eq', 'contains']],
            $document['collection']['filter']['fields'],
        );
        self::assertSame(
            ['defaultPage' => 1, 'defaultPerPage' => 10, 'maxPerPage' => 50],
            $document['collection']['pagination'],
        );

        // Output block: CustomerResource metadata, ref targets as registry
        // type handles with their href templates.
        self::assertSame('customer', $document['output']['type']);
        self::assertSame('id', $document['output']['idField']);
        $outByName = [];
        foreach ($document['output']['fields'] as $field) {
            $outByName[$field['name']] = $field;
        }
        self::assertSame('scalar', $outByName['id']['kind']);
        self::assertSame('Display name of the customer.', $outByName['name']['description']);
        self::assertSame('ref_one', $outByName['profile']['kind']);
        self::assertSame('ref_many', $outByName['addresses']['kind']);
        self::assertTrue($outByName['addresses']['list']);
    }

    #[Test]
    public function get_customer_document_has_output_but_no_collection(): void
    {
        $assembler = $this->assembler();
        $payloadRef = new \ReflectionClass(GetCustomerPayload::class);
        $route = $payloadRef->getAttributes()[0]->newInstance();

        $document = $assembler
            ->assemble(GetCustomerPayload::class, $route->responseWith)
            ->toDocument();

        self::assertArrayHasKey('input', $document);
        self::assertArrayHasKey('output', $document, 'singular route still resolves its resource');
        self::assertArrayNotHasKey('collection', $document, 'no allowlists on a singular response');
    }
}
