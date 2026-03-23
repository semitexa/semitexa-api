<?php

declare(strict_types=1);

namespace Semitexa\Api\Auth;

use Semitexa\Api\Domain\Model\MachineCredential;
use Semitexa\Core\Auth\AuthenticatableInterface;

/**
 * AuthenticatableInterface adapter that wraps a MachineCredential.
 *
 * The pipeline and authorization layer receive this as $context->authResult->user
 * for routes authenticated via machine credentials. Handlers and pipeline listeners
 * can cast to MachinePrincipal to access scope information.
 *
 * Example in a pipeline listener:
 * ```php
 * $user = $context->authResult?->user;
 * if ($user instanceof MachinePrincipal) {
 *     $user->credential->hasScope('orders:write') || throw new AccessDeniedException();
 * }
 * ```
 */
final readonly class MachinePrincipal implements AuthenticatableInterface
{
    public function __construct(
        public readonly MachineCredential $credential,
    ) {}

    public function getId(): string
    {
        return $this->credential->getId();
    }

    public function getAuthIdentifierName(): string
    {
        return 'machine_credential_id';
    }

    public function getAuthIdentifier(): string
    {
        return $this->credential->getId();
    }

    public function getClientName(): string
    {
        return $this->credential->getClientName();
    }

    /** @return list<string> */
    public function getScopes(): array
    {
        return $this->credential->getScopes();
    }

    public function hasScope(string $scope): bool
    {
        return $this->credential->hasScope($scope);
    }
}
