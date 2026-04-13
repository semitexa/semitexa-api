<?php

declare(strict_types=1);

namespace Semitexa\Api\Application\Db\MySQL\Model;

use Semitexa\Api\Domain\Model\MachineCredential;
use Semitexa\Orm\Attribute\AsMapper;
use Semitexa\Orm\Contract\TableModelMapper;

#[AsMapper(resourceModel: MachineCredentialTableModel::class, domainModel: MachineCredential::class)]
final class MachineCredentialMapper implements TableModelMapper
{
    public function toDomain(object $tableModel): object
    {
        $tableModel instanceof MachineCredentialTableModel || throw new \InvalidArgumentException('Unexpected table model.');

        return new MachineCredential(
            id: $tableModel->id,
            clientName: $tableModel->clientName,
            secretHash: $tableModel->secretHash,
            scopes: $tableModel->scopes,
            tenantId: $tableModel->tenantId,
            createdAt: $tableModel->createdAt,
            lastUsedAt: $tableModel->lastUsedAt,
            requestCount: $tableModel->requestCount,
            rotatedAt: $tableModel->rotatedAt,
            revokedAt: $tableModel->revokedAt,
        );
    }

    public function toTableModel(object $domainModel): object
    {
        $domainModel instanceof MachineCredential || throw new \InvalidArgumentException('Unexpected domain model.');

        return new MachineCredentialTableModel(
            id: $domainModel->getId(),
            clientName: $domainModel->getClientName(),
            secretHash: $domainModel->getSecretHash(),
            scopes: $domainModel->getScopes(),
            tenantId: $domainModel->getTenantId(),
            createdAt: $domainModel->getCreatedAt(),
            lastUsedAt: $domainModel->getLastUsedAt(),
            requestCount: $domainModel->getRequestCount(),
            rotatedAt: $domainModel->getRotatedAt(),
            revokedAt: $domainModel->getRevokedAt(),
        );
    }
}
