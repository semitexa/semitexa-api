<?php

declare(strict_types=1);

namespace Semitexa\Api\Application\Db\MySQL\Model;

use Semitexa\Api\Domain\Model\MachineCredential;
use Semitexa\Orm\Adapter\MySqlType;
use Semitexa\Orm\Attribute\Column;
use Semitexa\Orm\Attribute\FromTable;
use Semitexa\Orm\Attribute\Index;
use Semitexa\Orm\Contract\DomainMappable;
use Semitexa\Orm\Trait\HasTimestamps;
use Semitexa\Orm\Trait\HasUuidV7;

#[FromTable(name: 'api_machine_credentials', mapTo: MachineCredential::class)]
#[Index(columns: ['tenant_id', 'client_name'], name: 'idx_api_machine_credentials_tenant_client')]
#[Index(columns: ['tenant_id', 'revoked_at'], name: 'idx_api_machine_credentials_tenant_revoked')]
class MachineCredentialResource implements DomainMappable
{
    use HasUuidV7;
    use HasTimestamps;

    #[Column(type: MySqlType::Varchar, length: 255)]
    public string $client_name = '';

    #[Column(type: MySqlType::LongText)]
    public string $secret_hash = '';

    #[Column(type: MySqlType::Json)]
    public string $scopes_json = '[]';

    #[Column(type: MySqlType::Varchar, length: 64, nullable: true)]
    public ?string $tenant_id = null;

    #[Column(type: MySqlType::Datetime, nullable: true)]
    public ?\DateTimeImmutable $last_used_at = null;

    #[Column(type: MySqlType::Int)]
    public int $request_count = 0;

    #[Column(type: MySqlType::Datetime, nullable: true)]
    public ?\DateTimeImmutable $rotated_at = null;

    #[Column(type: MySqlType::Datetime, nullable: true)]
    public ?\DateTimeImmutable $revoked_at = null;

    public function toDomain(): MachineCredential
    {
        $scopes = json_decode($this->scopes_json, true);
        if (!is_array($scopes)) {
            $scopes = [];
        }

        return new MachineCredential(
            id: $this->id,
            clientName: $this->client_name,
            secretHash: $this->secret_hash,
            scopes: array_values(array_map('strval', $scopes)),
            tenantId: $this->tenant_id,
            createdAt: $this->created_at,
            lastUsedAt: $this->last_used_at,
            requestCount: $this->request_count,
            rotatedAt: $this->rotated_at,
            revokedAt: $this->revoked_at,
        );
    }

    public static function fromDomain(object $entity): static
    {
        assert($entity instanceof MachineCredential);

        $resource = new static();
        $resource->id = $entity->getId();
        $resource->client_name = $entity->getClientName();
        $resource->secret_hash = $entity->getSecretHash();
        $resource->scopes_json = json_encode($entity->getScopes(), JSON_THROW_ON_ERROR);
        $resource->tenant_id = $entity->getTenantId();
        $resource->last_used_at = $entity->getLastUsedAt();
        $resource->request_count = $entity->getRequestCount();
        $resource->rotated_at = $entity->getRotatedAt();
        $resource->revoked_at = $entity->getRevokedAt();
        $resource->created_at = $entity->getCreatedAt();

        return $resource;
    }
}
