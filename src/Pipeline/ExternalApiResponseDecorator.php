<?php

declare(strict_types=1);

namespace Semitexa\Api\Pipeline;

use Semitexa\Core\Attribute\SatisfiesServiceContract;
use Semitexa\Core\Contract\RouteResponseDecoratorInterface;
use Semitexa\Core\Discovery\ResolvedRouteMetadata;
use Semitexa\Core\Request;
use Semitexa\Core\HttpResponse;

#[SatisfiesServiceContract(of: RouteResponseDecoratorInterface::class)]
final class ExternalApiResponseDecorator implements RouteResponseDecoratorInterface
{
    public function decorate(HttpResponse $response, Request $request, ResolvedRouteMetadata $metadata): HttpResponse
    {
        if (!$metadata->hasExtension('external_api')) {
            return $response;
        }

        $apiVersion = $metadata->extensions['api_version'] ?? null;
        if (!is_array($apiVersion)) {
            return $response;
        }

        $headers = [
            'X-Api-Version' => (string) ($apiVersion['version'] ?? ''),
        ];

        if (($apiVersion['deprecated_since'] ?? null) !== null) {
            $headers['Deprecation'] = (string) $apiVersion['deprecated_since'];
        }

        if (($apiVersion['sunset_date'] ?? null) !== null) {
            $headers['Sunset'] = (string) $apiVersion['sunset_date'];
        }

        return $response->withHeaders($headers);
    }
}
