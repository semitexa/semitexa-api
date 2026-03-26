<?php

declare(strict_types=1);

namespace Semitexa\Api\Application\Db\MySQL\Repository;

use Semitexa\Api\Application\Db\MySQL\Model\MachineCredentialResource;
use Semitexa\Api\Domain\Contract\MachineCredentialRepositoryInterface;
use Semitexa\Api\Domain\Model\MachineCredential;
use Semitexa\Core\Attributes\SatisfiesRepositoryContract;
use Semitexa\Orm\Repository\AbstractRepository;
use Semitexa\Orm\Uuid\Uuid7;

#[SatisfiesRepositoryContract(of: MachineCredentialRepositoryInterface::class)]
final class MachineCredentialRepository extends AbstractRepository implements MachineCredentialRepositoryInterface
{
    protected function getResourceClass(): string
    {
        return MachineCredentialResource::class;
    }

    public function findById(int|string $id): ?MachineCredential
    {
        $pk = is_string($id) ? $this->normalizeId($id) : $id;

        /** @var MachineCredential|null $credential */
        $credential = $this->select()
            ->where($this->getPkColumn(), '=', $pk)
            ->fetchOne();

        return $credential;
    }

    public function findByClientName(string $clientName): ?MachineCredential
    {
        /** @var MachineCredential|null $credential */
        $credential = $this->select()
            ->where('client_name', '=', $clientName)
            ->whereNull('revoked_at')
            ->orderBy('created_at', 'DESC')
            ->fetchOne();

        return $credential;
    }

    public function save(object $credential): void
    {
        parent::save($credential);
    }

    public function update(MachineCredential $credential): void
    {
        parent::save($credential);
    }

    public function findAllActive(?string $tenantId = null): array
    {
        $query = $this->select()
            ->whereNull('revoked_at')
            ->orderBy('created_at', 'DESC');

        if ($tenantId !== null) {
            $query->where('tenant_id', '=', $tenantId);
        }

        /** @var list<MachineCredential> $credentials */
        $credentials = $query->fetchAll();

        return $credentials;
    }

    private function normalizeId(string $id): string
    {
        if (strlen($id) === 36 && str_contains($id, '-')) {
            return Uuid7::toBytes($id);
        }

        return $id;
    }
}
