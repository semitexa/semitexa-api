<?php

declare(strict_types=1);

namespace Semitexa\Api\Auth;

use Semitexa\Api\Domain\Contract\MachineCredentialRepositoryInterface;
use Semitexa\Auth\Attribute\AsAuthHandler;
use Semitexa\Auth\Handler\AuthHandlerInterface;
use Semitexa\Core\Attribute\InjectAsReadonly;
use Semitexa\Core\Auth\AuthResult;
use Semitexa\Core\Request;

/**
 * Authentication handler for machine-to-machine (M2M) API credentials.
 *
 * Integrates into the existing AuthBootstrapper discovery mechanism via
 * #[AsAuthHandler] — no second bootstrap runtime is introduced.  The handler
 * runs alongside user-session handlers in first_match order (or whichever
 * strategy is configured); lower priority values run first.
 *
 * Token format: "Bearer {credentialId}:{rawSecret}" in the Authorization header.
 *
 * The handler is intentionally low-priority (50) so that user session handlers
 * (typically priority 0) run first.  A request authenticated as a user bypasses
 * machine auth entirely.
 *
 * Security notes:
 * - Secret comparison is performed via MachineCredential::verifySecret(), which
 *   delegates to password_verify() — constant-time by design.
 * - Revoked credentials are rejected even when the secret is correct.
 * - Usage is recorded (lastUsedAt + requestCount) on every successful auth;
 *   the repository implementation decides whether to flush synchronously or defer.
 */
#[AsAuthHandler(priority: 50)]
final class MachineAuthHandler implements AuthHandlerInterface
{
    #[InjectAsReadonly]
    protected ?MachineCredentialRepositoryInterface $credentials = null;

    public function handle(object $payload): ?AuthResult
    {
        if ($this->credentials === null) {
            // Repository not installed; skip gracefully.
            return null;
        }

        $token = $this->extractToken($payload);
        if ($token === null) {
            return null;
        }

        [$credentialId, $rawSecret] = $token;

        $credential = $this->credentials->findById($credentialId);
        if ($credential === null) {
            // No credential with this ID — let the next handler try.
            return null;
        }

        if ($credential->isRevoked()) {
            return AuthResult::failed('Machine credential has been revoked.');
        }

        if (!$credential->verifySecret($rawSecret)) {
            return AuthResult::failed('Invalid machine credential secret.');
        }

        $credential = $credential->recordUsage(new \DateTimeImmutable());
        $this->credentials->update($credential);

        return AuthResult::success(new MachinePrincipal($credential));
    }

    /**
     * Extract credentialId and rawSecret from the Authorization header.
     * Expects format: "Bearer {id}:{secret}"
     *
     * Returns null when the header is absent or malformed — signals this handler
     * to skip rather than fail the request.
     *
     * @return array{0: string, 1: string}|null
     */
    private function extractToken(object $payload): ?array
    {
        // AuthBootstrapper passes the payload object, not the raw Request.
        // Access the request via setHttpRequest convention or fall back to header check.
        $request = method_exists($payload, 'getHttpRequest') ? $payload->getHttpRequest() : null;
        if (!$request instanceof Request) {
            return null;
        }

        $authHeader = $request->getHeader('Authorization');
        if ($authHeader === null || !str_starts_with($authHeader, 'Bearer ')) {
            return null;
        }

        $rawToken = substr($authHeader, 7);
        $colonPos = strpos($rawToken, ':');
        if ($colonPos === false || $colonPos === 0 || $colonPos === strlen($rawToken) - 1) {
            return null;
        }

        return [
            substr($rawToken, 0, $colonPos),
            substr($rawToken, $colonPos + 1),
        ];
    }
}
