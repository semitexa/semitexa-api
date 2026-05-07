<?php

declare(strict_types=1);

namespace Semitexa\Api\Application\Console\Command;

use Semitexa\Api\OpenApi\OpenApiDocumentBuilder;
use Semitexa\Core\Attribute\AsCommand;
use Semitexa\Core\Attribute\InjectAsReadonly;
use Semitexa\Core\Console\BaseCommand;
use Semitexa\Core\Discovery\ClassDiscovery;
use Semitexa\Core\Environment;
use Semitexa\Core\Resource\Metadata\ResourceMetadataCacheFile;
use Semitexa\Core\Resource\Metadata\ResourceMetadataRegistry;
use Semitexa\Core\Resource\Metadata\ResourceMetadataSourceFingerprint;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'openapi:dump',
    description: 'Dump the OpenAPI document for routes that produce Resource DTO JSON responses.',
)]
final class DumpOpenApiCommand extends BaseCommand
{
    #[InjectAsReadonly]
    protected OpenApiDocumentBuilder $builder;

    #[InjectAsReadonly]
    protected ResourceMetadataRegistry $registry;

    #[InjectAsReadonly]
    protected ClassDiscovery $classDiscovery;

    #[InjectAsReadonly]
    protected ResourceMetadataCacheFile $cache;

    #[InjectAsReadonly]
    protected Environment $environment;

    #[InjectAsReadonly]
    protected ResourceMetadataSourceFingerprint $fingerprint;

    protected function configure(): void
    {
        $this->setName('openapi:dump')
            ->setDescription('Dump the OpenAPI document for routes that produce Resource DTO JSON responses.')
            ->addOption('output', 'o', InputOption::VALUE_REQUIRED, 'Write the document to this file (defaults to stdout).')
            ->addOption('title', null, InputOption::VALUE_REQUIRED, 'OpenAPI info.title (default: "Semitexa Resource API")', 'Semitexa Resource API')
            ->addOption('api-version', null, InputOption::VALUE_REQUIRED, 'OpenAPI info.version (default: "0.0.0")', '0.0.0');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $title   = (string) $input->getOption('title');
        $version = (string) $input->getOption('api-version');
        $outPath = $input->getOption('output');

        try {
            $this->registry->ensureWarmed(
                discovery:   $this->classDiscovery,
                cache:       $this->cache,
                production:  $this->environment->appEnv === 'prod',
                fingerprint: $this->fingerprint,
            );
            $json = $this->builder->toJson($title, $version);
        } catch (\Throwable $e) {
            $io->error('OpenAPI generation failed: ' . $e->getMessage());
            return self::FAILURE;
        }

        if (is_string($outPath) && $outPath !== '') {
            $abs = $this->absolutize($outPath);
            $dir = dirname($abs);
            if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) {
                $io->error(sprintf('Could not create directory %s', $dir));
                return self::FAILURE;
            }
            if (file_put_contents($abs, $json, LOCK_EX) === false) {
                $io->error(sprintf('Could not write to %s', $abs));
                return self::FAILURE;
            }
            $io->success(sprintf('OpenAPI document written to %s.', $outPath));
            return self::SUCCESS;
        }

        $output->write($json);
        return self::SUCCESS;
    }

    private function absolutize(string $path): string
    {
        if (str_starts_with($path, '/') || preg_match('#^[A-Za-z]:[\\\\/]#', $path) === 1) {
            return $path;
        }
        return rtrim($this->getProjectRoot(), '/') . '/' . ltrim($path, '/');
    }
}
