<?php

declare(strict_types=1);

namespace Semitexa\Api\Tests\Unit\Auth;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Semitexa\Api\Application\Service\Credential\CredentialLifetime;
use Semitexa\Api\Application\Service\Credential\MachineCredentialIssuer;
use Semitexa\Api\Attribute\RequiresScope;
use Semitexa\Api\Auth\MachineAuthHandler;
use Semitexa\Api\Auth\MachinePrincipal;
use Semitexa\Api\Domain\Contract\MachineCredentialRepositoryInterface;
use Semitexa\Api\Domain\Contract\MachineCredentialUsageRecorderInterface;
use Semitexa\Api\Domain\Model\MachineCredential;
use Semitexa\Api\Pipeline\RequiresScopeListener;
use Semitexa\Core\Auth\AuthResult;
use Semitexa\Core\Discovery\DiscoveredRoute;
use Semitexa\Core\Exception\AccessDeniedException;
use Semitexa\Core\Pipeline\RequestPipelineContext;
use Semitexa\Core\Request;
use Semitexa\Core\Tenant\Layer\TenantLayerInterface;
use Semitexa\Core\Tenant\Layer\TenantLayerValueInterface;
use Semitexa\Core\Tenant\TenantContextInterface;
use Semitexa\Core\Tenant\TenantContextStoreInterface;

/**
 * A credential is issued by a command now, expires when told to, and a route
 * says which scopes it needs instead of every handler checking by hand.
 */
final class MachineCredentialLifecycleTest extends TestCase
{
    #[Test]
    public function an_issued_token_authenticates_and_only_its_hash_is_stored(): void
    {
        $store = new LifecycleCredentials();
        $issued = $this->issuer($store)->issue('erp-sync', ['orders:read', ' orders:read ', ''], 'tenant-a');

        [$id, $secret] = explode(':', $issued->token, 2);
        self::assertSame($issued->credential->getId(), $id);
        self::assertGreaterThanOrEqual(43, strlen($secret), '32 random bytes');
        self::assertSame(['orders:read'], $store->byId[$id]->getScopes());
        self::assertStringNotContainsString($secret, $store->byId[$id]->getSecretHash());
        self::assertTrue($this->authenticate($store, $issued->token, 'tenant-a')?->success);
    }

    #[Test]
    public function an_expired_credential_does_not_authenticate(): void
    {
        $store = new LifecycleCredentials();
        $issued = $this->issuer($store)->issue('erp-sync', ['orders:read'], null, new \DateTimeImmutable('+1 hour'));
        self::assertTrue($this->authenticate($store, $issued->token)?->success);

        $store->byId[$issued->credential->getId()] = new MachineCredential(
            id: $issued->credential->getId(),
            clientName: 'erp-sync',
            secretHash: $issued->credential->getSecretHash(),
            scopes: ['orders:read'],
            expiresAt: new \DateTimeImmutable('-1 second'),
        );

        $result = $this->authenticate($store, $issued->token);
        self::assertFalse($result?->success);
        self::assertStringContainsString('expired', (string) $result?->reason);
    }

    #[Test]
    public function a_usage_record_keeps_the_expiry(): void
    {
        $credential = new MachineCredential(id: 'c', clientName: 'x', secretHash: 'h', expiresAt: new \DateTimeImmutable('2030-01-01'));

        self::assertEquals(new \DateTimeImmutable('2030-01-01'), $credential->recordUsage(new \DateTimeImmutable())->getExpiresAt());
        self::assertEquals(new \DateTimeImmutable('2030-01-01'), $credential->rotateSecretHash('h2')->getExpiresAt());
        self::assertEquals(new \DateTimeImmutable('2030-01-01'), $credential->revoke()->getExpiresAt());
    }

    #[Test]
    public function a_rotated_secret_replaces_the_old_one_and_a_revoked_credential_is_refused(): void
    {
        $store = new LifecycleCredentials();
        $issuer = $this->issuer($store);
        $first = $issuer->issue('erp-sync', ['orders:read']);

        $second = $issuer->rotate($store->byId[$first->credential->getId()]);
        self::assertFalse($this->authenticate($store, $first->token)?->success);
        self::assertTrue($this->authenticate($store, $second->token)?->success);

        $issuer->revoke($store->byId[$first->credential->getId()]);
        self::assertFalse($this->authenticate($store, $second->token)?->success);
    }

    #[Test]
    public function a_successful_authentication_writes_only_the_usage_columns(): void
    {
        // update() wrote the whole row the request read before its password
        // check, putting back a revoke or rotation made in the meantime.
        $store = new RecordingCredentials();
        $issued = $this->issuer($store)->issue('erp-sync', ['orders:read']);
        $store->updates = 0;

        self::assertTrue($this->authenticate($store, $issued->token)?->success);
        self::assertSame(0, $store->updates, 'the whole row was written back');
        self::assertSame([$issued->credential->getId()], $store->usage);
    }

    #[Test]
    public function a_malformed_id_names_no_credential_instead_of_crashing_the_lookup(): void
    {
        // A 36-character id with a dash is converted as a UUID by the ORM:
        // `zzzzzzzz-…` threw a TypeError there and every page answered 500.
        self::assertFalse(MachineAuthHandler::canBeAnId('zzzzzzzz-zzzz-zzzz-zzzz-zzzzzzzzzzzz'));
        self::assertFalse(MachineAuthHandler::canBeAnId('0123456789-0123456789-0123456789-012'));
        self::assertTrue(MachineAuthHandler::canBeAnId('01a15ba8-f130-79fb-a68f-5d2d4257f99a'));
        self::assertTrue(MachineAuthHandler::canBeAnId('cred-1'), 'an id that is no UUID is the repository\'s to answer');

        $store = new LifecycleCredentials();
        self::assertNull($this->authenticate($store, 'zzzzzzzz-zzzz-zzzz-zzzz-zzzzzzzzzzzz:secret'));
    }

    #[Test]
    public function a_lifetime_is_days_hours_or_minutes(): void
    {
        $now = new \DateTimeImmutable('2026-10-21 00:00:00');

        self::assertEquals(new \DateTimeImmutable('2027-01-19 00:00:00'), CredentialLifetime::expiresAt('90d', $now));
        self::assertEquals(new \DateTimeImmutable('2026-10-21 12:00:00'), CredentialLifetime::expiresAt('12h', $now));
        foreach (['', '0d', '-1d', '90', 'd', '1w', '90 d'] as $notALifetime) {
            self::assertNull(CredentialLifetime::expiresAt($notALifetime, $now), $notALifetime);
        }
    }

    #[Test]
    public function a_route_requiring_scopes_admits_only_a_credential_holding_all_of_them(): void
    {
        $listener = new RequiresScopeListener();

        $listener->handle($this->context(new ScopedOrdersPayload(), ['orders:read', 'orders:write']));
        $this->addToAssertionCount(1);

        foreach ([['orders:read'], [], null] as $scopes) {
            try {
                $listener->handle($this->context(new ScopedOrdersPayload(), $scopes));
                self::fail('admitted with ' . json_encode($scopes));
            } catch (AccessDeniedException $denied) {
                self::assertStringContainsString($scopes === null ? 'machine credential' : 'orders:write', $denied->getMessage());
            }
        }

        $listener->handle($this->context(new UnscopedPayload(), null));
    }

    /** @param list<string>|null $scopes null = not a machine credential */
    private function context(object $payload, ?array $scopes): RequestPipelineContext
    {
        $context = new RequestPipelineContext(
            requestDto: $payload,
            route: (new \ReflectionClass(DiscoveredRoute::class))->newInstanceWithoutConstructor(),
            request: new Request('GET', '/api/orders', [], [], [], [], []),
        );
        if ($scopes !== null) {
            $context->authResult = AuthResult::successAsService(new MachinePrincipal(
                new MachineCredential(id: 'c', clientName: 'erp', secretHash: 'h', scopes: $scopes),
            ));
        }

        return $context;
    }

    private function issuer(MachineCredentialRepositoryInterface $store): MachineCredentialIssuer
    {
        $issuer = new MachineCredentialIssuer();
        (new \ReflectionProperty($issuer, 'credentials'))->setValue($issuer, $store);

        return $issuer;
    }

    private function authenticate(MachineCredentialRepositoryInterface $store, string $token, ?string $tenant = null): ?AuthResult
    {
        $handler = new MachineAuthHandler();
        (new \ReflectionProperty($handler, 'credentials'))->setValue($handler, $store);
        (new \ReflectionProperty($handler, 'tenantContextStore'))->setValue($handler, new LifecycleTenantStore($tenant === null ? null : new LifecycleTenantContext($tenant)));

        return $handler->handle(new class (new Request('GET', '/api/orders', ['Authorization' => 'Bearer ' . $token], [], [], [], [])) {
            public function __construct(private readonly Request $request) {}
            public function getHttpRequest(): Request { return $this->request; }
        });
    }
}

#[RequiresScope('orders:read', 'orders:write')]
final class ScopedOrdersPayload
{
}

final class UnscopedPayload
{
}

final class LifecycleCredentials implements MachineCredentialRepositoryInterface
{
    /** @var array<string, MachineCredential> */
    public array $byId = [];

    public function findById(string $id): ?MachineCredential { return $this->byId[$id] ?? null; }
    public function findByClientName(string $clientName): ?MachineCredential { return null; }
    public function save(MachineCredential $credential): void { $this->byId[$credential->getId()] = $credential; }
    public function update(MachineCredential $credential): void { $this->byId[$credential->getId()] = $credential; }
    public function findAllActive(?string $tenantId = null): array { return array_values($this->byId); }
}

final class LifecycleTenantContext implements TenantContextInterface
{
    public function __construct(private string $tenantId) {}
    public function getTenantId(): string { return $this->tenantId; }
    public function getLayer(TenantLayerInterface $layer): ?TenantLayerValueInterface { return null; }
    public function hasLayer(TenantLayerInterface $layer): bool { return false; }
}

final class LifecycleTenantStore implements TenantContextStoreInterface
{
    public function __construct(private ?TenantContextInterface $context) {}
    public function get(): TenantContextInterface { return $this->context ?? throw new \LogicException('no tenant'); }
    public function tryGet(): ?TenantContextInterface { return $this->context; }
    public function set(TenantContextInterface $context): void { $this->context = $context; }
    public function clear(): void { $this->context = null; }
}

final class RecordingCredentials implements MachineCredentialRepositoryInterface, MachineCredentialUsageRecorderInterface
{
    /** @var array<string, MachineCredential> */
    public array $byId = [];
    public int $updates = 0;
    /** @var list<string> */
    public array $usage = [];

    public function findById(string $id): ?MachineCredential { return $this->byId[$id] ?? null; }
    public function findByClientName(string $clientName): ?MachineCredential { return null; }
    public function save(MachineCredential $credential): void { $this->byId[$credential->getId()] = $credential; }
    public function update(MachineCredential $credential): void { $this->updates++; $this->byId[$credential->getId()] = $credential; }
    public function findAllActive(?string $tenantId = null): array { return array_values($this->byId); }
    public function recordUsage(string $credentialId, \DateTimeImmutable $at): void { $this->usage[] = $credentialId; }
}
