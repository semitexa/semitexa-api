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

    public function testAnUnknownCredentialIdStillPaysForAPasswordCheck(): void
    {
        // A lookup miss used to return in microseconds while a live credential
        // costs a full password_verify(): latency alone told which ids exist.
        $dummy = new ReflectionProperty(MachineAuthHandler::class, 'dummyHash');
        $saved = $dummy->getValue();

        try {
            $dummy->setValue(null, null);

            $repository = new class implements MachineCredentialRepositoryInterface {
                public function findById(string $id): ?MachineCredential { return null; }
                public function findByClientName(string $clientName): ?MachineCredential { return null; }
                public function save(MachineCredential $credential): void {}
                public function update(MachineCredential $credential): void {}
                public function findAllActive(?string $tenantId = null): array { return []; }
            };

            $handler = new MachineAuthHandler();
            (new ReflectionProperty($handler, 'credentials'))->setValue($handler, $repository);

            $payload = new class(new Request('GET', '/api/x', ['Authorization' => 'Bearer nobody:guess'], [], [], [], [])) {
                public function __construct(private readonly Request $request) {}
                public function getHttpRequest(): Request
                {
                    return $this->request;
                }
            };

            // First call builds the dummy hash; it must be the credential algorithm.
            self::assertNull($handler->handle($payload));
            $hash = $dummy->getValue();
            self::assertIsString($hash);
            // Both branches pin a real algorithm: an empty or unknown hash
            // would make both timed checks cheap and the test meaningless.
            self::assertSame(
                \defined('PASSWORD_ARGON2ID') ? PASSWORD_ARGON2ID : PASSWORD_DEFAULT,
                password_get_info($hash)['algo'],
            );

            // The hash is cached now, so a second miss costs only the verify.
            // Skipping password_verify() would make it microseconds against a
            // verify that costs milliseconds: the margin is orders of magnitude.
            $verifyStart = hrtime(true);
            password_verify('guess', $hash);
            $verifyCost = hrtime(true) - $verifyStart;

            $missStart = hrtime(true);
            self::assertNull($handler->handle($payload));
            $missCost = hrtime(true) - $missStart;

            self::assertGreaterThan(
                intdiv($verifyCost, 4),
                $missCost,
                'the miss path must run password_verify against the dummy hash',
            );
        } finally {
            $dummy->setValue(null, $saved);
        }
    }
}
