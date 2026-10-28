<?php

declare(strict_types=1);

namespace Semitexa\Api\Domain\Contract;

/**
 * Records a successful authentication without writing the rest of the row.
 *
 * update() writes every column from the copy the request read, so a request
 * in flight while an operator revoked or rotated the credential wrote the old
 * revoked_at and secret_hash back: the revoke said done and the leaked token
 * kept working. A repository that implements this touches only the audit
 * columns, and never those of a revoked credential.
 */
interface MachineCredentialUsageRecorderInterface
{
    public function recordUsage(string $credentialId, \DateTimeImmutable $at): void;
}
