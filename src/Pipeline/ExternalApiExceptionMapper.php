<?php

declare(strict_types=1);

namespace Semitexa\Api\Pipeline;

use Semitexa\Core\Attributes\InjectAsReadonly;
use Semitexa\Core\Attributes\SatisfiesServiceContract;
use Semitexa\Core\Contract\ExceptionResponseMapperInterface;
use Semitexa\Core\Discovery\ResolvedRouteMetadata;
use Semitexa\Core\Exception\DomainException;
use Semitexa\Core\Exception\RateLimitException;
use Semitexa\Core\Pipeline\ExceptionMapper;
use Semitexa\Core\Request;
use Semitexa\Core\Response;

/**
 * Machine-facing error envelope formatter for external API routes.
 *
 * Routes that carry the 'external_api' extension flag (added by #[ExternalApi])
 * receive a stable JSON envelope suitable for API consumers and M2M clients:
 *
 * ```json
 * {
 *   "error": {
 *     "code":       "not_found",
 *     "message":    "User 42 was not found.",
 *     "context":    {},
 *     "request_id": "...",
 *     "docs_url":   null
 *   }
 * }
 * ```
 *
 * Routes that do NOT carry the 'external_api' flag fall through to the Core
 * ExceptionMapper to preserve existing behavior exactly.
 *
 * This class overrides the ExceptionResponseMapperInterface binding. Only one
 * mapper is active at a time; semitexa-api provides this implementation when installed.
 */
#[SatisfiesServiceContract(of: ExceptionResponseMapperInterface::class)]
final class ExternalApiExceptionMapper implements ExceptionResponseMapperInterface
{
    #[InjectAsReadonly]
    protected ExceptionMapper $coreMapper;

    public function map(\Throwable $e, Request $request, ResolvedRouteMetadata $metadata): Response
    {
        // Non-external routes keep Core default semantics.
        if (!$metadata->hasExtension('external_api')) {
            return $this->coreMapper->map($e, $request, $metadata);
        }

        if ($e instanceof DomainException) {
            return $this->mapDomainException($e, $request, $metadata);
        }

        // Unknown exceptions are re-thrown; Application logs and converts them.
        throw $e;
    }

    private function mapDomainException(
        DomainException $e,
        Request $request,
        ResolvedRouteMetadata $metadata,
    ): Response {
        $status = $e->getStatusCode();

        $body = [
            'error' => [
                'code'       => $e->getErrorCode(),
                'message'    => $e->getMessage(),
                'context'    => $e->getErrorContext() ?: new \stdClass(),
                'request_id' => $this->extractRequestId($request),
                'docs_url'   => null,
            ],
        ];

        $response = Response::json($body, $status->value);

        if ($e instanceof RateLimitException) {
            $response = $response->withHeaders(['Retry-After' => (string) $e->getRetryAfter()]);
        }

        return $response;
    }

    private function extractRequestId(Request $request): ?string
    {
        return $request->getHeader('X-Request-Id') ?: $request->getHeader('X-Correlation-Id') ?: null;
    }
}
