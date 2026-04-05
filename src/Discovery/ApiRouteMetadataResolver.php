<?php

declare(strict_types=1);

namespace Semitexa\Api\Discovery;

use InvalidArgumentException;
use ReflectionClass;
use Semitexa\Api\Attribute\ApiVersion;
use Semitexa\Api\Attribute\ExternalApi;
use Semitexa\Core\Attribute\InjectAsReadonly;
use Semitexa\Core\Attribute\SatisfiesServiceContract;
use Semitexa\Core\Contract\RouteMetadataResolverInterface;
use Semitexa\Core\Discovery\DefaultRouteMetadataResolver;
use Semitexa\Core\Discovery\DiscoveredRoute;
use Semitexa\Core\Discovery\ResolvedRouteMetadata;

/**
 * semitexa-api override of RouteMetadataResolverInterface.
 *
 * Delegates to DefaultRouteMetadataResolver for Core metadata, then enriches the
 * result with ExternalApi and ApiVersion attribute data read from the Payload class.
 * The enriched data is placed in the ResolvedRouteMetadata $extensions bag under
 * well-known keys so that ExternalApiExceptionMapper and response header middleware
 * can react without touching discovery internals.
 *
 * Extension keys written:
 * - 'external_api': ['version' => string, 'description' => string]
 * - 'api_version':  ['version' => string, 'deprecated_since' => string|null, 'sunset_date' => string|null]
 *
 * Attributes are cached per payload class (worker-scoped static) to avoid
 * repeated reflection work under Swoole.
 */
#[SatisfiesServiceContract(of: RouteMetadataResolverInterface::class)]
final class ApiRouteMetadataResolver implements RouteMetadataResolverInterface
{
    #[InjectAsReadonly]
    protected DefaultRouteMetadataResolver $coreResolver;

    /** @var array<string, array<string,mixed>> className => extensions map */
    private static array $cache = [];

    /**
     * @param DiscoveredRoute|array<string, mixed> $route
     */
    public function resolve(mixed $route): ResolvedRouteMetadata
    {
        if ($route instanceof DiscoveredRoute) {
            $discoveredRoute = $route;
        } elseif (is_array($route)) {
            $discoveredRoute = DiscoveredRoute::fromArray($route);
        } else {
            throw new InvalidArgumentException('Expected a discovered route array or DiscoveredRoute instance.');
        }

        $base = $this->coreResolver->resolve($discoveredRoute);

        $requestClass = $discoveredRoute->requestClass;
        if ($requestClass === '' || !class_exists($requestClass)) {
            return $base;
        }

        if (!array_key_exists($requestClass, self::$cache)) {
            self::$cache[$requestClass] = $this->buildExtensions($requestClass);
        }

        $extensions = self::$cache[$requestClass];

        if ($extensions === []) {
            return $base;
        }

        return $base->withExtensions($extensions);
    }

    /** @return array<string,mixed> */
    private function buildExtensions(string $requestClass): array
    {
        $extensions = [];
        $ref = new ReflectionClass($requestClass);

        $externalApiAttrs = $ref->getAttributes(ExternalApi::class);
        if ($externalApiAttrs !== []) {
            /** @var ExternalApi $attr */
            $attr = $externalApiAttrs[0]->newInstance();
            $extensions['external_api'] = [
                'version'     => $attr->version,
                'description' => $attr->description,
            ];
        }

        $versionAttrs = $ref->getAttributes(ApiVersion::class);
        if ($versionAttrs !== []) {
            /** @var ApiVersion $attr */
            $attr = $versionAttrs[0]->newInstance();
            $extensions['api_version'] = [
                'version'          => $attr->version,
                'deprecated_since' => $attr->deprecatedSince,
                'sunset_date'      => $attr->sunsetDate,
            ];
        }

        return $extensions;
    }
}
