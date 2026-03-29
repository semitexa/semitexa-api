<?php

declare(strict_types=1);

namespace Semitexa\Api\Application\Db\MySQL\Model;

use Semitexa\Orm\Adapter\MySqlType;
use Semitexa\Orm\Attribute\Column;
use Semitexa\Orm\Attribute\FromTable;
use Semitexa\Orm\Attribute\PrimaryKey;
use Semitexa\Orm\Metadata\HasColumnReferences;
use Semitexa\Orm\Metadata\HasRelationReferences;

#[FromTable(name: 'api_machine_credentials')]
final readonly class MachineCredentialTableModel
{
    use HasColumnReferences;
    use HasRelationReferences;

    /**
     * @param list<string> $scopes
     */
    public function __construct(
        #[PrimaryKey(strategy: 'uuid')]
        #[Column(type: MySqlType::Binary, length: 16)]
        public string $id,

        #[Column(name: 'client_name', type: MySqlType::Varchar, length: 255)]
        public string $clientName,

        #[Column(name: 'secret_hash', type: MySqlType::LongText)]
        public string $secretHash,

        #[Column(name: 'scopes_json', type: MySqlType::Json)]
        public array $scopes,

        #[Column(name: 'tenant_id', type: MySqlType::Varchar, length: 64, nullable: true)]
        public ?string $tenantId,

        #[Column(name: 'created_at', type: MySqlType::Datetime)]
        public \DateTimeImmutable $createdAt,

        #[Column(name: 'last_used_at', type: MySqlType::Datetime, nullable: true)]
        public ?\DateTimeImmutable $lastUsedAt,

        #[Column(name: 'request_count', type: MySqlType::Int)]
        public int $requestCount,

        #[Column(name: 'rotated_at', type: MySqlType::Datetime, nullable: true)]
        public ?\DateTimeImmutable $rotatedAt,

        #[Column(name: 'revoked_at', type: MySqlType::Datetime, nullable: true)]
        public ?\DateTimeImmutable $revokedAt,
    ) {}
}
