<?php

declare(strict_types=1);

namespace Semitexa\Api;

use Semitexa\Core\Attribute\Capability;

/**
 * What this package offers, for the capability catalog.
 *
 * Without this the package is invisible to anyone whose project has not
 * installed it - which is precisely the audience worth telling, since they are
 * the ones about to build it by hand. The convention is one `Capabilities` class
 * per package: a definite place to look, and a definite place for a guard to
 * check.
 *
 * Nothing reads this at runtime.
 */
#[Capability(
    id: 'api.external',
    summary: 'An external API surface: #[ExternalApi] routes with #[ApiVersion], machine credentials and collection contracts.',
    useWhen: 'Something outside your own frontend consumes these routes and needs a stable, versioned contract.',
    avoidWhen: 'The caller is your own SSR pages, which already share the payload and resource types.',
    replaces: [
        'a parallel set of controllers duplicating handlers for machine callers',
        'an API key check and a version prefix bolted onto each route by hand',
    ],
)]
final class Capabilities
{
}
