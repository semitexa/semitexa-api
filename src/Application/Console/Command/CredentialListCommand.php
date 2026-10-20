<?php

declare(strict_types=1);

namespace Semitexa\Api\Application\Console\Command;

use Semitexa\Api\Domain\Contract\MachineCredentialRepositoryInterface;
use Semitexa\Core\Attribute\AsCommand;
use Semitexa\Core\Attribute\InjectAsReadonly;
use Semitexa\Core\Console\BaseCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'api:credential:list',
    description: 'List machine credentials that are not revoked (expired ones marked): scopes, tenant, expiry, last use.',
)]
final class CredentialListCommand extends BaseCommand
{
    #[InjectAsReadonly]
    protected MachineCredentialRepositoryInterface $credentials;

    protected function configure(): void
    {
        $this->setName('api:credential:list')
            ->setDescription('List machine credentials that are not revoked (expired ones marked): scopes, tenant, expiry, last use.')
            ->addOption('tenant', null, InputOption::VALUE_REQUIRED, 'Only those that authenticate on this tenant (bound to it, or to every tenant)')
            ->addOption('json', null, InputOption::VALUE_NONE, 'Print as JSON');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $tenant = $input->getOption('tenant');
        $now = new \DateTimeImmutable();
        $rows = [];
        $tenant = is_string($tenant) && $tenant !== '' ? $tenant : null;
        foreach ($this->credentials->findAllActive() as $credential) {
            // A credential valid on every tenant works on this one too.
            if ($tenant !== null && $credential->getTenantId() !== null && $credential->getTenantId() !== $tenant) {
                continue;
            }
            $rows[] = [
                'id' => $credential->getId(),
                'client' => $credential->getClientName(),
                'scopes' => $credential->getScopes(),
                'tenant' => $credential->getTenantId(),
                'expires_at' => $credential->getExpiresAt()?->format(DATE_ATOM),
                'expired' => $credential->isExpired($now),
                'last_used_at' => $credential->getLastUsedAt()?->format(DATE_ATOM),
                'requests' => $credential->getRequestCount(),
            ];
        }

        if ($input->getOption('json')) {
            $output->writeln((string) json_encode($rows, JSON_UNESCAPED_SLASHES), OutputInterface::OUTPUT_RAW);

            return self::SUCCESS;
        }

        $io = new SymfonyStyle($input, $output);
        if ($rows === []) {
            $io->writeln('No machine credentials. Issue one with api:credential:issue.');

            return self::SUCCESS;
        }
        $io->table(
            ['id', 'client', 'scopes', 'tenant', 'expires', 'last used', 'requests'],
            array_map(static fn (array $r): array => [
                $r['id'],
                $r['client'],
                implode(', ', $r['scopes']),
                $r['tenant'] ?? 'every',
                $r['expires_at'] === null ? 'never' : ($r['expired'] ? 'EXPIRED ' : '') . substr($r['expires_at'], 0, 16),
                $r['last_used_at'] === null ? '—' : substr($r['last_used_at'], 0, 16),
                $r['requests'],
            ], $rows),
        );

        return self::SUCCESS;
    }
}
