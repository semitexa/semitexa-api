<?php

declare(strict_types=1);

namespace Semitexa\Api\Tests\Unit\Domain;

use PHPUnit\Framework\TestCase;
use Semitexa\Api\Domain\Model\MachineCredential;

final class MachineCredentialTest extends TestCase
{
    public function testMutationMethodsReturnNewImmutableInstances(): void
    {
        $credential = new MachineCredential(
            id: 'cred-1',
            clientName: 'ci-worker',
            secretHash: password_hash('secret-123', PASSWORD_ARGON2ID),
            scopes: ['users:read'],
        );

        $used = $credential->recordUsage(new \DateTimeImmutable('2026-03-28 10:00:00'));
        $revoked = $used->revoke(new \DateTimeImmutable('2026-03-28 11:00:00'));
        $rotated = $revoked->rotateSecretHash('new-hash', new \DateTimeImmutable('2026-03-28 12:00:00'));

        self::assertSame(0, $credential->getRequestCount());
        self::assertNull($credential->getLastUsedAt());
        self::assertSame(1, $used->getRequestCount());
        self::assertNotNull($used->getLastUsedAt());
        self::assertNotNull($revoked->getRevokedAt());
        self::assertSame('new-hash', $rotated->getSecretHash());
        self::assertNotSame($credential, $used);
        self::assertNotSame($used, $revoked);
        self::assertNotSame($revoked, $rotated);
    }

    public function testNegativeRequestCountIsClampedToZero(): void
    {
        $credential = new MachineCredential(
            id: 'cred-2',
            clientName: 'batch-worker',
            secretHash: password_hash('secret-456', PASSWORD_ARGON2ID),
            requestCount: -5,
        );

        self::assertSame(0, $credential->getRequestCount());
    }
}
