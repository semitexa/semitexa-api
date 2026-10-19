<?php

declare(strict_types=1);

namespace Semitexa\Api\Tests\Unit\Auth;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Semitexa\Api\Auth\MachineAuthHandler;
use Semitexa\Api\Auth\MachinePrincipal;
use Semitexa\Api\Domain\Contract\MachineCredentialRepositoryInterface;
use Semitexa\Api\Domain\Model\MachineCredential;
use Semitexa\Api\Pipeline\ExternalApiExceptionMapper;
use Semitexa\Core\Discovery\ResolvedRouteMetadata;
use Semitexa\Core\Exception\AccessDeniedException;
use Semitexa\Core\Request;
use Semitexa\Core\Tenant\Layer\TenantLayerInterface;
use Semitexa\Core\Tenant\Layer\TenantLayerValueInterface;
use Semitexa\Core\Tenant\TenantContextInterface;
use Semitexa\Core\Tenant\TenantContextStoreInterface;

/**
 * What a machine credential may do once it authenticates (tk-parity-proof-gaps):
 * the scopes it carries, a handler refusing one it lacks with the API's 403
 * envelope, and the tenant it was issued for.
 */
final class MachineCredentialAccessTest extends TestCase
{
    #[Test]
    public function the_authenticated_principal_carries_exactly_the_granted_scopes(): void
    {
        $principal = $this->authenticate($this->credential(scopes: ['orders:read']));

        self::assertSame(['orders:read'], $principal->getScopes());
        self::assertTrue($principal->hasScope('orders:read'));
        self::assertFalse($principal->hasScope('orders:write'));
        self::assertFalse($principal->hasScope('Orders:read'), 'scopes match exactly');
        self::assertFalse($this->authenticate($this->credential(scopes: ['orders:*']))->hasScope('orders:read'), 'no wildcards: a scope is granted by name');
        self::assertFalse($this->authenticate($this->credential(scopes: []))->hasScope('orders:read'), 'no scopes, no access');
    }

    #[Test]
    public function a_handler_refusing_a_missing_scope_answers_403_in_the_api_envelope(): void
    {
        $principal = $this->authenticate($this->credential(scopes: ['orders:read']));

        try {
            // The check MachinePrincipal documents, as a handler writes it.
            $principal->hasScope('orders:write') || throw new AccessDeniedException('This credential may not write orders.');
            self::fail('a credential without orders:write was let through');
        } catch (AccessDeniedException $denied) {
            $response = (new ExternalApiExceptionMapper())->map($denied, new Request('POST', '/api/orders', ['Accept' => 'application/json'], [], [], [], []), $this->externalRoute());
        }

        self::assertSame(403, $response->getStatusCode());
        $body = json_decode($response->getContent(), true);
        self::assertSame('This credential may not write orders.', $body['error']['message'] ?? null);
    }

    /**
     * REGRESSION. A credential stores the tenant it was issued for, and nothing
     * read it: a bearer token is not bound to a host the way a session cookie
     * is, so tenant A's integration authenticated on tenant B's API, where the
     * request's tenant scope is B's data.
     */
    #[Test]
    public function a_credential_issued_for_one_tenant_does_not_authenticate_on_another(): void
    {
        $result = $this->handler($this->credential(tenantId: 'tenant-a'), requestTenant: 'tenant-b')->handle($this->bearer());

        self::assertNotNull($result);
        self::assertFalse($result->success);
        self::assertStringContainsString('tenant', (string) $result->reason);
    }

    #[Test]
    public function a_bound_credential_is_refused_where_no_tenant_was_resolved(): void
    {
        // The base domain of a multi-tenant install, or a store that was never injected:
        // the request runs as "default", which is not the tenant the credential belongs to.
        $unresolved = $this->handler($this->credential(tenantId: 'tenant-a'), requestTenant: null)->handle($this->bearer());
        $noStore = new MachineAuthHandler();
        (new \ReflectionProperty($noStore, 'credentials'))->setValue($noStore, new AccessCredentials($this->credential(tenantId: 'tenant-a')));

        self::assertFalse($unresolved?->success);
        self::assertFalse($noStore->handle($this->bearer())?->success);
    }

    #[Test]
    public function an_empty_tenant_is_not_platform_wide(): void
    {
        // Only null means "every tenant"; an empty string is a broken binding, refused.
        self::assertFalse($this->handler($this->credential(tenantId: ''), requestTenant: 'tenant-b')->handle($this->bearer())?->success);
    }

    #[Test]
    public function a_credential_authenticates_on_its_own_tenant_and_an_unbound_one_on_any(): void
    {
        $own = $this->handler($this->credential(tenantId: 'tenant-a'), requestTenant: 'tenant-a')->handle($this->bearer());
        $unbound = $this->handler($this->credential(tenantId: null), requestTenant: 'tenant-b')->handle($this->bearer());
        $singleTenant = $this->handler($this->credential(tenantId: 'default'), requestTenant: null)->handle($this->bearer());

        self::assertTrue($own?->success);
        self::assertTrue($unbound?->success, 'a credential with no tenant is platform-wide');
        self::assertTrue($singleTenant?->success, 'a single-tenant install runs as "default"');
    }

    private function authenticate(MachineCredential $credential): MachinePrincipal
    {
        $result = $this->handler($credential, requestTenant: null)->handle($this->bearer());
        self::assertTrue($result?->success, (string) $result?->reason);
        self::assertInstanceOf(MachinePrincipal::class, $result->user);

        return $result->user;
    }

    private function handler(MachineCredential $credential, ?string $requestTenant): MachineAuthHandler
    {
        $handler = new MachineAuthHandler();
        (new \ReflectionProperty($handler, 'credentials'))->setValue($handler, new AccessCredentials($credential));
        $tenants = new \ReflectionProperty($handler, 'tenantContextStore');
        $tenants->setValue($handler, new AccessTenantStore($requestTenant === null ? null : new AccessTenantContext($requestTenant)));

        return $handler;
    }

    /** @param list<string> $scopes */
    private function credential(array $scopes = ['orders:read'], ?string $tenantId = null): MachineCredential
    {
        return new MachineCredential(id: 'cred-1', clientName: 'erp', secretHash: password_hash('s3cret', PASSWORD_BCRYPT, ['cost' => 4]), scopes: $scopes, tenantId: $tenantId);
    }

    private function bearer(): object
    {
        return new class (new Request('GET', '/api/orders', ['Authorization' => 'Bearer cred-1:s3cret'], [], [], [], [])) {
            public function __construct(private readonly Request $request) {}
            public function getHttpRequest(): Request { return $this->request; }
        };
    }

    private function externalRoute(): ResolvedRouteMetadata
    {
        return new ResolvedRouteMetadata(path: '/api/orders', name: 'orders.create', methods: ['POST'], requestClass: 'Payload', responseClass: 'Response', produces: ['json'], consumes: null, handlers: [], requirements: [], extensions: ['external_api' => ['version' => 'v1']]);
    }
}

final class AccessCredentials implements MachineCredentialRepositoryInterface
{
    public function __construct(private MachineCredential $credential) {}
    public function findById(string $id): ?MachineCredential { return $id === $this->credential->getId() ? $this->credential : null; }
    public function findByClientName(string $clientName): ?MachineCredential { return null; }
    public function save(MachineCredential $credential): void {}
    public function update(MachineCredential $credential): void { $this->credential = $credential; }
    public function findAllActive(?string $tenantId = null): array { return [$this->credential]; }
}

final class AccessTenantContext implements TenantContextInterface
{
    public function __construct(private string $tenantId) {}
    public function getTenantId(): string { return $this->tenantId; }
    public function getLayer(TenantLayerInterface $layer): ?TenantLayerValueInterface { return null; }
    public function hasLayer(TenantLayerInterface $layer): bool { return false; }
}

final class AccessTenantStore implements TenantContextStoreInterface
{
    public function __construct(private ?TenantContextInterface $context) {}
    public function get(): TenantContextInterface { return $this->context ?? throw new \LogicException('no tenant'); }
    public function tryGet(): ?TenantContextInterface { return $this->context; }
    public function set(TenantContextInterface $context): void { $this->context = $context; }
    public function clear(): void { $this->context = null; }
}
