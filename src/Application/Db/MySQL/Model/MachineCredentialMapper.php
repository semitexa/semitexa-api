<?php

declare(strict_types=1);

namespace Semitexa\Api\Application\Db\MySQL\Model;

use Semitexa\Api\Domain\Model\MachineCredential;
use Semitexa\Orm\Attribute\AsMapper;
use Semitexa\Orm\Contract\ResourceModelMapperInterface;

#[AsMapper(resourceModel: MachineCredentialResourceModel::class, domainModel: MachineCredential::class)]
final class MachineCredentialMapper implements ResourceModelMapperInterface
{
    public function toDomain(object $resourceModel): object
    {
        $resourceModel instanceof MachineCredentialResourceModel || throw new \InvalidArgumentException('Unexpected resource model.');

        return new MachineCredential(
            id: $resourceModel->id,
            clientName: $resourceModel->clientName,
            secretHash: $resourceModel->secretHash,
            scopes: $resourceModel->scopes,
            tenantId: $resourceModel->tenantId,
            createdAt: $resourceModel->createdAt,
            lastUsedAt: $resourceModel->lastUsedAt,
            requestCount: $resourceModel->requestCount,
            rotatedAt: $resourceModel->rotatedAt,
            revokedAt: $resourceModel->revokedAt,
        );
    }

    public function toSourceModel(object $domainModel): object
    {
        $domainModel instanceof MachineCredential || throw new \InvalidArgumentException('Unexpected domain model.');

        return new MachineCredentialResourceModel(
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
