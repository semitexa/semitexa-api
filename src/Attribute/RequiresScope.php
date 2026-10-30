<?php

declare(strict_types=1);

namespace Semitexa\Api\Attribute;

use Attribute;

/**
 * Only a machine credential that holds every one of these scopes reaches the
 * handler; any other caller gets 403. Scopes match by exact name, no wildcards.
 * Put it on a service payload (#[AsServicePayload]): a user session carries no
 * scopes, so it is refused too.
 *
 * @api
 */
#[Attribute(Attribute::TARGET_CLASS)]
final readonly class RequiresScope
{
    /** @var non-empty-list<string> */
    public array $scopes;

    public function __construct(string ...$scopes)
    {
        $scopes = array_values(array_filter(array_map('trim', $scopes), static fn (string $s): bool => $s !== ''));
        if ($scopes === []) {
            throw new \InvalidArgumentException('#[RequiresScope] needs at least one scope.');
        }
        $this->scopes = $scopes;
    }
}
