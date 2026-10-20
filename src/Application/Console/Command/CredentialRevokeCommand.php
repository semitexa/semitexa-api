<?php

declare(strict_types=1);

namespace Semitexa\Api\Application\Console\Command;

use Semitexa\Api\Application\Service\Credential\MachineCredentialIssuer;
use Semitexa\Api\Auth\MachineAuthHandler;
use Semitexa\Api\Domain\Contract\MachineCredentialRepositoryInterface;
use Semitexa\Core\Attribute\AsCommand;
use Semitexa\Core\Attribute\InjectAsReadonly;
use Semitexa\Core\Console\BaseCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'api:credential:revoke',
    description: 'Revoke a machine credential, or with --rotate give it a new secret.',
)]
final class CredentialRevokeCommand extends BaseCommand
{
    #[InjectAsReadonly]
    protected MachineCredentialRepositoryInterface $credentials;

    #[InjectAsReadonly]
    protected MachineCredentialIssuer $issuer;

    protected function configure(): void
    {
        $this->setName('api:credential:revoke')
            ->setDescription('Revoke a machine credential, or with --rotate give it a new secret.')
            ->addArgument('id', InputArgument::REQUIRED, 'The credential id (the part of the token before the colon)')
            ->addOption('rotate', null, InputOption::VALUE_NONE, 'Keep the credential, replace its secret, print the new token once');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $id = (string) $input->getArgument('id');
        $credential = MachineAuthHandler::canBeAnId($id) ? $this->credentials->findById($id) : null;
        if ($credential === null || $credential->isRevoked()) {
            $io->error("No active machine credential {$id}.");

            return self::FAILURE;
        }

        if ($input->getOption('rotate')) {
            $issued = $this->issuer->rotate($credential);
            $io->success("New secret for {$credential->getClientName()}; the old one no longer works.");
            $io->writeln('  Authorization: Bearer ' . $issued->token);
            $io->warning('This is the only time the token is shown.');

            return self::SUCCESS;
        }

        $this->issuer->revoke($credential);
        $io->success("Revoked {$credential->getClientName()} ({$id}).");

        return self::SUCCESS;
    }
}
