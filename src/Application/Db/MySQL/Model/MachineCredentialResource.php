<?php

declare(strict_types=1);

namespace Semitexa\Api\Application\Db\MySQL\Model;

use Semitexa\Api\Domain\Model\MachineCredential;
use Semitexa\Orm\Adapter\MySqlType;
use Semitexa\Orm\Attribute\Column;
use Semitexa\Orm\Attribute\FromTable;
use Semitexa\Orm\Attribute\Index;
use Semitexa\Orm\Trait\HasTimestamps;
use Semitexa\Orm\Trait\HasUuidV7;

#[FromTable(name: 'api_machine_credentials')]
#[Index(columns: ['tenant_id', 'client_name'], name: 'idx_api_machine_credentials_tenant_client')]
#[Index(columns: ['tenant_id', 'revoked_at'], name: 'idx_api_machine_credentials_tenant_revoked')]
class MachineCredentialResource
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
}
