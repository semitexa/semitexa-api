<?php

declare(strict_types=1);

namespace Semitexa\Api\Application\Service\Credential;

use Semitexa\Api\Domain\Model\MachineCredential;

/** A credential just issued, and the one moment its token can be read. */
final readonly class IssuedMachineCredential
{
    public function __construct(
        public MachineCredential $credential,
        /** `{id}:{secret}`, to send as `Authorization: Bearer …`; only the secret's hash is stored. */
        public string $token,
    ) {}
}
