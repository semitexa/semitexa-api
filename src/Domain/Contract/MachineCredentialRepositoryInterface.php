<?php

declare(strict_types=1);

namespace Semitexa\Api\Domain\Contract;

use Semitexa\Api\Domain\Model\MachineCredential;

/**
 * Repository contract for machine credential persistence.
 *
 * Implementations are provided by the storage layer (ORM, Doctrine, Redis, etc.)
 * and registered via #[SatisfiesRepositoryContract(of: MachineCredentialRepositoryInterface::class)].
 *
 * Lookup always returns null rather than throwing NotFoundException so that the
 * auth handler can distinguish "not found" (no credential) from "found but revoked"
 * in a single call without catching exceptions in hot auth-check code.
 */
interface MachineCredentialRepositoryInterface
{
    /**
     * Find a credential by its opaque ID.
     * Returns null when the credential does not exist or has been purged.
     */
    public function findById(string $id): ?MachineCredential;

    /**
     * Find a credential by the client name it was issued to.
     * When multiple credentials exist for the same name, the most recently created
     * active one is returned.
     */
    public function findByClientName(string $clientName): ?MachineCredential;

    /**
     * Persist a new credential.  Called once at creation time.
     */
    public function save(object $credential): void;

    /**
     * Persist audit field changes (lastUsedAt, requestCount) and revocation status.
     * Called on every successful authentication and on revoke.
     */
    public function update(MachineCredential $credential): void;

    /**
     * Return all active (non-revoked) credentials, optionally scoped to a tenant.
     *
     * @return list<MachineCredential>
     */
    public function findAllActive(?string $tenantId = null): array;
}
