<?php

declare(strict_types=1);

namespace Semitexa\Api\Application\Service\Credential;

use Semitexa\Api\Domain\Contract\MachineCredentialRepositoryInterface;
use Semitexa\Api\Domain\Model\MachineCredential;
use Semitexa\Core\Attribute\AsService;
use Semitexa\Core\Attribute\InjectAsReadonly;
use Semitexa\Orm\Application\Service\Uuid7;

/**
 * Issues, rotates and revokes machine credentials. The secret is 32 random
 * bytes, shown once in the returned token and stored only as an Argon2id hash
 * (bcrypt where PHP lacks Argon2), the algorithm MachineAuthHandler expects.
 */
#[AsService]
final class MachineCredentialIssuer
{
    #[InjectAsReadonly]
    protected MachineCredentialRepositoryInterface $credentials;

    /** @param list<string> $scopes */
    public function issue(string $clientName, array $scopes, ?string $tenantId = null, ?\DateTimeImmutable $expiresAt = null): IssuedMachineCredential
    {
        $clientName = trim($clientName);
        if ($clientName === '') {
            throw new \InvalidArgumentException('A machine credential needs a client name.');
        }
        $scopes = array_values(array_unique(array_filter(array_map('trim', $scopes), static fn (string $s): bool => $s !== '')));
        if ($expiresAt !== null && $expiresAt <= new \DateTimeImmutable()) {
            throw new \InvalidArgumentException('A machine credential cannot expire in the past.');
        }

        $secret = self::secret();
        $credential = new MachineCredential(
            id: Uuid7::generate(),
            clientName: $clientName,
            secretHash: self::hash($secret),
            scopes: $scopes,
            tenantId: $tenantId,
            expiresAt: $expiresAt,
        );
        $this->credentials->save($credential);

        return new IssuedMachineCredential($credential, $credential->getId() . ':' . $secret);
    }

    /** A new secret for the same credential; the old one stops working at once. */
    public function rotate(MachineCredential $credential): IssuedMachineCredential
    {
        $secret = self::secret();
        $rotated = $credential->rotateSecretHash(self::hash($secret));
        $this->credentials->update($rotated);

        return new IssuedMachineCredential($rotated, $rotated->getId() . ':' . $secret);
    }

    public function revoke(MachineCredential $credential): MachineCredential
    {
        $revoked = $credential->revoke();
        $this->credentials->update($revoked);

        return $revoked;
    }

    private static function secret(): string
    {
        return rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
    }

    private static function hash(string $secret): string
    {
        return password_hash($secret, \defined('PASSWORD_ARGON2ID') ? PASSWORD_ARGON2ID : PASSWORD_DEFAULT);
    }
}
