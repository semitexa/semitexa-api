<?php

declare(strict_types=1);

namespace Semitexa\Api\Application\Console\Command;

use Semitexa\Api\Application\Service\Credential\CredentialLifetime;
use Semitexa\Api\Application\Service\Credential\MachineCredentialIssuer;
use Semitexa\Core\Attribute\AsCommand;
use Semitexa\Core\Attribute\InjectAsReadonly;
use Semitexa\Core\Console\BaseCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'api:credential:issue',
    description: 'Issue a machine credential and print its Bearer token, once.',
)]
final class CredentialIssueCommand extends BaseCommand
{
    #[InjectAsReadonly]
    protected MachineCredentialIssuer $issuer;

    protected function configure(): void
    {
        $this->setName('api:credential:issue')
            ->setDescription('Issue a machine credential and print its Bearer token, once.')
            ->addArgument('client', InputArgument::REQUIRED, 'Who it is for, e.g. erp-sync')
            ->addOption('scope', 's', InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'A scope it holds (repeatable), e.g. orders:read')
            ->addOption('tenant', null, InputOption::VALUE_REQUIRED, 'The only tenant it authenticates on')
            ->addOption('all-tenants', null, InputOption::VALUE_NONE, 'Valid on every tenant instead (one of the two is required)')
            ->addOption('expires-in', null, InputOption::VALUE_REQUIRED, 'Lifetime: 90d, 12h or 30m (default: no expiry)')
            ->addOption('json', null, InputOption::VALUE_NONE, 'Print the result as JSON');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $expiresIn = $input->getOption('expires-in');
        $expiresAt = null;
        if (is_string($expiresIn) && $expiresIn !== '') {
            $expiresAt = CredentialLifetime::expiresAt($expiresIn);
            if ($expiresAt === null) {
                $io->error("--expires-in={$expiresIn} is not a lifetime: use 90d, 12h or 30m.");

                return self::FAILURE;
            }
        }

        // Required either way: a script passing --tenant=$TENANT with the
        // variable unset would otherwise issue a credential valid everywhere.
        $tenant = $input->getOption('tenant');
        $allTenants = (bool) $input->getOption('all-tenants');
        if ($allTenants === ($tenant !== null)) {
            $io->error('Name the tenant it is for with --tenant=<id>, or pass --all-tenants for one valid on every tenant.');

            return self::FAILURE;
        }
        if ($tenant !== null && trim((string) $tenant) === '') {
            $io->error('--tenant is empty. Name a tenant, or pass --all-tenants.');

            return self::FAILURE;
        }
        /** @var list<string> $scopes */
        $scopes = (array) $input->getOption('scope');

        try {
            $issued = $this->issuer->issue((string) $input->getArgument('client'), $scopes, $allTenants ? null : trim((string) $tenant), $expiresAt);
        } catch (\InvalidArgumentException $e) {
            $io->error($e->getMessage());

            return self::FAILURE;
        }

        $credential = $issued->credential;
        if ($input->getOption('json')) {
            $output->writeln((string) json_encode([
                'id' => $credential->getId(),
                'client' => $credential->getClientName(),
                'scopes' => $credential->getScopes(),
                'tenant' => $credential->getTenantId(),
                'expires_at' => $credential->getExpiresAt()?->format(DATE_ATOM),
                'token' => $issued->token,
            ], JSON_UNESCAPED_SLASHES), OutputInterface::OUTPUT_RAW);

            return self::SUCCESS;
        }

        $io->success("Issued {$credential->getClientName()} ({$credential->getId()}).");
        $io->definitionList(
            ['scopes' => $credential->getScopes() === [] ? '(none: it can reach no #[RequiresScope] route)' : implode(', ', $credential->getScopes())],
            ['tenant' => $credential->getTenantId() ?? 'every tenant'],
            ['expires' => $credential->getExpiresAt()?->format('Y-m-d H:i') ?? 'never'],
        );
        $io->writeln('Send it as:');
        $io->writeln('  Authorization: Bearer ' . $issued->token);
        $io->warning('This is the only time the token is shown; only a hash of its secret is kept.');

        return self::SUCCESS;
    }
}
