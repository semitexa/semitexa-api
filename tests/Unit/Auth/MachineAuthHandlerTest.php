<?php

declare(strict_types=1);

namespace Semitexa\Api\Tests\Unit\Auth;

use PHPUnit\Framework\TestCase;
use ReflectionProperty;
use Semitexa\Api\Auth\MachineAuthHandler;
use Semitexa\Api\Auth\MachinePrincipal;
use Semitexa\Api\Domain\Contract\MachineCredentialRepositoryInterface;
use Semitexa\Api\Domain\Model\MachineCredential;
use Semitexa\Core\Request;

final class MachineAuthHandlerTest extends TestCase
{
    public function testHandleSkipsWhenCredentialRepositoryIsNotInjected(): void
    {
        $handler = new MachineAuthHandler();

        $payload = new class(new Request(
            'GET',
            '/api/platform/users',
            ['Authorization' => 'Bearer cred-1:secret-123'],
            [],
            [],
            [],
            [],
        )) {
            public function __construct(private readonly Request $request) {}
            public function getHttpRequest(): Request
            {
                return $this->request;
            }
        };

        self::assertNull($handler->handle($payload));
    }

    public function testHandleAuthenticatesValidBearerCredential(): void
    {
        $credential = new MachineCredential(
            id: 'cred-1',
            clientName: 'ci-worker',
            secretHash: password_hash('secret-123', PASSWORD_ARGON2ID),
            scopes: ['users:read'],
        );

        $repository = new class($credential) implements MachineCredentialRepositoryInterface {
            public function __construct(private readonly MachineCredential $credential) {}
            public function findById(string $id): ?MachineCredential
            {
                return $id === $this->credential->getId() ? $this->credential : null;
            }
            public function findByClientName(string $clientName): ?MachineCredential
            {
                return $clientName === $this->credential->getClientName() ? $this->credential : null;
            }
            public function save(MachineCredential $credential): void {}
            public function update(MachineCredential $credential): void {}
            public function findAllActive(?string $tenantId = null): array
            {
                return [$this->credential];
            }
        };

        $handler = new MachineAuthHandler();
        $property = new ReflectionProperty($handler, 'credentials');
        $property->setValue($handler, $repository);

        $payload = new class(new Request(
            'GET',
            '/api/platform/users',
            ['Authorization' => 'Bearer cred-1:secret-123'],
            [],
            [],
            [],
            [],
        )) {
            public function __construct(private readonly Request $request) {}
            public function getHttpRequest(): Request
            {
                return $this->request;
            }
        };

        $result = $handler->handle($payload);

        self::assertNotNull($result);
        self::assertTrue($result->success);
        self::assertInstanceOf(MachinePrincipal::class, $result->user);
        self::assertTrue($result->user->hasScope('users:read'));
    }

    public function testHandleSkipsMalformedBearerTokenWithoutSecret(): void
    {
        $credential = new MachineCredential(
            id: 'cred-1',
            clientName: 'ci-worker',
            secretHash: password_hash('secret-123', PASSWORD_ARGON2ID),
            scopes: ['users:read'],
        );

        $repository = new class($credential) implements MachineCredentialRepositoryInterface {
            public function __construct(private readonly MachineCredential $credential) {}
            public function findById(string $id): ?MachineCredential
            {
                return $id === $this->credential->getId() ? $this->credential : null;
            }
            public function findByClientName(string $clientName): ?MachineCredential
            {
                return $clientName === $this->credential->getClientName() ? $this->credential : null;
            }
            public function save(MachineCredential $credential): void {}
            public function update(MachineCredential $credential): void {}
            public function findAllActive(?string $tenantId = null): array
            {
                return [$this->credential];
            }
        };

        $handler = new MachineAuthHandler();
        $property = new ReflectionProperty($handler, 'credentials');
        $property->setValue($handler, $repository);

        $payload = new class(new Request(
            'GET',
            '/api/platform/users',
            ['Authorization' => 'Bearer cred-1:'],
            [],
            [],
            [],
            [],
        )) {
            public function __construct(private readonly Request $request) {}
            public function getHttpRequest(): Request
            {
                return $this->request;
            }
        };

        self::assertNull($handler->handle($payload));
    }
}
