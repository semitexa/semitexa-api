<?php

declare(strict_types=1);

namespace Semitexa\Api\Domain\Model;

/**
 * Domain entity representing a machine (M2M) API credential.
 *
 * A MachineCredential is created once and associated with a named client
 * (e.g. an integration partner, CI system, or service account).  The raw
 * secret is presented only at creation time; only the hash is persisted.
 *
 * Scope semantics:
 * - Scopes are free-form strings.  Convention: 'resource:action', e.g. 'orders:read'.
 * - An empty scopes array means the credential has no access.
 * - Packages that need scope enforcement should inject MachineCredentialRepositoryInterface
 *   and check $credential->hasScope('orders:read').
 *
 * Audit fields:
 * - $lastUsedAt and $requestCount are updated by the auth handler on every
 *   successful authentication.  Write them via the setter; never mutate directly.
 */
final class MachineCredential
{
    private \DateTimeImmutable $createdAt;
    private ?\DateTimeImmutable $lastUsedAt = null;
    private int $requestCount = 0;
    private ?\DateTimeImmutable $rotatedAt = null;
    private ?\DateTimeImmutable $revokedAt = null;

    /**
     * @param list<string> $scopes       Scopes granted to this credential
     * @param string|null  $tenantId     Optional tenant association (null = global)
     * @param string       $secretHash   Argon2id hash of the raw secret (never the secret itself)
     */
    public function __construct(
        private readonly string $id,
        private readonly string $clientName,
        private string $secretHash,
        private array $scopes = [],
        private readonly ?string $tenantId = null,
        ?\DateTimeImmutable $createdAt = null,
        ?\DateTimeImmutable $lastUsedAt = null,
        int $requestCount = 0,
        ?\DateTimeImmutable $rotatedAt = null,
        ?\DateTimeImmutable $revokedAt = null,
    ) {
        $this->createdAt = $createdAt ?? new \DateTimeImmutable();
        $this->lastUsedAt = $lastUsedAt;
        $this->requestCount = max(0, $requestCount);
        $this->rotatedAt = $rotatedAt;
        $this->revokedAt = $revokedAt;
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getClientName(): string
    {
        return $this->clientName;
    }

    public function getSecretHash(): string
    {
        return $this->secretHash;
    }

    /** @return list<string> */
    public function getScopes(): array
    {
        return $this->scopes;
    }

    public function hasScope(string $scope): bool
    {
        return in_array($scope, $this->scopes, true);
    }

    public function getTenantId(): ?string
    {
        return $this->tenantId;
    }

    public function isRevoked(): bool
    {
        return $this->revokedAt !== null;
    }

    public function isActive(): bool
    {
        return $this->revokedAt === null;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getLastUsedAt(): ?\DateTimeImmutable
    {
        return $this->lastUsedAt;
    }

    public function getRequestCount(): int
    {
        return $this->requestCount;
    }

    public function getRotatedAt(): ?\DateTimeImmutable
    {
        return $this->rotatedAt;
    }

    public function getRevokedAt(): ?\DateTimeImmutable
    {
        return $this->revokedAt;
    }

    // --- Mutation ---

    public function revoke(?\DateTimeImmutable $revokedAt = null): void
    {
        $this->revokedAt = $revokedAt ?? new \DateTimeImmutable();
    }

    /**
     * Rotate the secret hash.  Call only after generating and presenting the new
     * raw secret to the caller exactly once.
     */
    public function rotateSecretHash(string $newHash, ?\DateTimeImmutable $rotatedAt = null): void
    {
        $this->secretHash = $newHash;
        $this->rotatedAt = $rotatedAt ?? new \DateTimeImmutable();
    }

    /**
     * Update audit fields after a successful authentication.
     * The auth handler calls this; the repository is responsible for persisting it.
     */
    public function recordUsage(\DateTimeImmutable $at): void
    {
        $this->lastUsedAt = $at;
        $this->requestCount++;
    }

    /**
     * Verify a raw secret against the stored hash.
     * Uses constant-time comparison to prevent timing attacks.
     */
    public function verifySecret(string $rawSecret): bool
    {
        return password_verify($rawSecret, $this->secretHash);
    }
}
