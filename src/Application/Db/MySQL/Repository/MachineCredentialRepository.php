<?php

declare(strict_types=1);

namespace Semitexa\Api\Application\Db\MySQL\Repository;

use Semitexa\Api\Application\Db\MySQL\Model\MachineCredentialResourceModel;
use Semitexa\Api\Domain\Contract\MachineCredentialRepositoryInterface;
use Semitexa\Api\Domain\Model\MachineCredential;
use Semitexa\Core\Attribute\InjectAsReadonly;
use Semitexa\Core\Attribute\SatisfiesRepositoryContract;
use Semitexa\Orm\OrmManager;
use Semitexa\Orm\Query\Direction;
use Semitexa\Orm\Repository\DomainRepository;

#[SatisfiesRepositoryContract(of: MachineCredentialRepositoryInterface::class)]
final class MachineCredentialRepository implements MachineCredentialRepositoryInterface
{
    #[InjectAsReadonly]
    protected OrmManager $orm;

    private ?DomainRepository $repository = null;

    public function findById(string $id): ?MachineCredential
    {
        /** @var MachineCredential|null */
        return $this->repository()->findById($id);
    }

    public function findByClientName(string $clientName): ?MachineCredential
    {
        /** @var MachineCredential|null */
        return $this->repository()->query()
            ->where(MachineCredentialResourceModel::column('clientName'), \Semitexa\Orm\Query\Operator::Equals, $clientName)
            ->whereNull(MachineCredentialResourceModel::column('revokedAt'))
            ->orderBy(MachineCredentialResourceModel::column('createdAt'), Direction::Desc)
            ->fetchOneAs(MachineCredential::class, $this->orm()->getMapperRegistry());
    }

    public function save(MachineCredential $credential): void
    {
        $this->repository()->insert($credential);
    }

    public function update(MachineCredential $credential): void
    {
        $this->repository()->update($credential);
    }

    public function findAllActive(?string $tenantId = null): array
    {
        $query = $this->repository()->query()
            ->whereNull(MachineCredentialResourceModel::column('revokedAt'))
            ->orderBy(MachineCredentialResourceModel::column('createdAt'), Direction::Desc);

        if ($tenantId !== null) {
            $query->where(MachineCredentialResourceModel::column('tenantId'), \Semitexa\Orm\Query\Operator::Equals, $tenantId);
        }

        /** @var list<MachineCredential> $credentials */
        $credentials = $query->fetchAllAs(MachineCredential::class, $this->orm()->getMapperRegistry());

        return $credentials;
    }

    private function repository(): DomainRepository
    {
        return $this->repository ??= $this->orm()->repository(
            MachineCredentialResourceModel::class,
            MachineCredential::class,
        );
    }

    private function orm(): OrmManager
    {
        return $this->orm ?? throw new \RuntimeException('OrmManager not injected into ' . self::class . '.');
    }
}
