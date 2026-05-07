<?php

declare(strict_types=1);

namespace Semitexa\Api\Tests\Fixtures\Union;

use Semitexa\Core\Resource\Attribute\ResourceId;
use Semitexa\Core\Resource\Attribute\ResourceObject;
use Semitexa\Core\Resource\Attribute\ResourceUnion;
use Semitexa\Core\Resource\ResourceIdentity;
use Semitexa\Core\Resource\ResourceObjectInterface;
use Semitexa\Core\Resource\ResourceRef;
use Semitexa\Core\Resource\ResourceRefList;

/**
 * Polymorphic-relation host. Uses {@see ResourceUnion} — the dedicated
 * attribute for multi-target relations. The earlier in-test fixtures
 * passed an array to `#[ResourceRef(target: ...)]` and 500-cascaded
 * because `ResourceRef` is single-target by contract; the framework's
 * union path is `ResourceUnion(targets: [...], list: …)` and is the
 * shape `ResourceMetadataExtractor` expects.
 *
 * - `author`   = single union ref; NOT expandable (the IncludeTokenCollector
 *   test pins that this case is omitted from the include token list).
 * - `mentions` = list union ref; expandable (token IS emitted).
 *
 * Default discriminator (`type`) is intentional — the schema generator
 * test asserts the produced OpenAPI mapping uses `'user'` / `'bot'`,
 * which come from the variants' `#[ResourceObject(type: …)]`.
 */
#[ResourceObject(type: 'comment')]
final readonly class CommentResource implements ResourceObjectInterface
{
    public function __construct(
        #[ResourceId]
        public string $id,

        // Non-nullable on purpose: the schema generator wraps nullable
        // schemas in an outer `oneOf [..., {type:'null'}]` envelope, but
        // the union assertions in ResourceSchemaGeneratorTest pin the
        // raw discriminator+oneOf shape — making the field nullable here
        // would push that shape one level deeper and trip the assertion.
        #[ResourceUnion(
            targets: [UserResource::class, BotResource::class],
            list: false,
            include: 'author',
        )]
        public ResourceRef $author = new ResourceRef(new ResourceIdentity('user', '0')),

        #[ResourceUnion(
            targets: [UserResource::class, BotResource::class],
            list: true,
            expandable: true,
            include: 'mentions',
        )]
        public ResourceRefList $mentions = new ResourceRefList(),
    ) {
    }
}
