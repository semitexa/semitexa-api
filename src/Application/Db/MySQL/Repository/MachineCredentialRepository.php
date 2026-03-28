<?php

declare(strict_types=1);

namespace Semitexa\Api\Application\Db\MySQL\Repository;

use Semitexa\Api\Application\Db\MySQL\Model\MachineCredentialTableModel;
use Semitexa\Api\Domain\Contract\MachineCredentialRepositoryInterface;
use Semitexa\Api\Domain\Model\MachineCredential;
use Semitexa\Core\Attributes\InjectAsReadonly;
use Semitexa\Core\Attributes\SatisfiesRepositoryContract;
use Semitexa\Orm\OrmManager;
use Semitexa\Orm\Query\Direction;
use Semitexa\Orm\Repository\DomainRepository;

#[SatisfiesRepositoryContract(of: MachineCredentialRepositoryInterface::class)]
final class MachineCredentialRepository implements MachineCredentialRepositoryInterface
{
    #[InjectAsReadonly]
    protected ?OrmManager $orm = null;

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
            ->where(MachineCredentialTableModel::column('clientName'), \Semitexa\Orm\Query\Operator::Equals, $clientName)
            ->whereNull(MachineCredentialTableModel::column('revokedAt'))
            ->orderBy(MachineCredentialTableModel::column('createdAt'), Direction::Desc)
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
            ->whereNull(MachineCredentialTableModel::column('revokedAt'))
            ->orderBy(MachineCredentialTableModel::column('createdAt'), Direction::Desc);

        if ($tenantId !== null) {
            $query->where(MachineCredentialTableModel::column('tenantId'), \Semitexa\Orm\Query\Operator::Equals, $tenantId);
        }

        /** @var list<MachineCredential> $credentials */
        $credentials = $query->fetchAllAs(MachineCredential::class, $this->orm()->getMapperRegistry());

        return $credentials;
    }

    private function repository(): DomainRepository
    {
        return $this->repository ??= $this->orm()->repository(
            MachineCredentialTableModel::class,
            MachineCredential::class,
        );
    }

    private function orm(): OrmManager
    {
        return $this->orm ??= new OrmManager();
    }
}
