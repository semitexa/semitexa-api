<?php

declare(strict_types=1);

namespace Semitexa\Api\Tests\Unit;

use FilesystemIterator;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * Architecture guard for the semitexa-api package.
 *
 * Package tests must test the package. Application-module / demo
 * behaviour (Article CRUD, Showcase pages, route demos) belongs to the
 * owning app module under `src/modules/...` and is tested at the
 * repo-root `tests/Playground/...` location, not inside this package's
 * tests directory.
 *
 * This guard is narrow on purpose. It pins the boundary so the previous
 * drift — a complete REST Article CRUD + Showcase fixture stack living
 * under `packages/semitexa-api/tests/Fixtures/Demo/` and a parallel test
 * tree at `packages/semitexa-api/tests/Demo/` (later renamed to
 * `tests/Integration/`) — cannot quietly come back. It is modelled on
 * `Semitexa\Graphql\Tests\Unit\PackageStructureTest` and on the
 * `Semitexa\Graphql\Tests\Unit\Http\EndpointOwnershipTest` precedent.
 */
final class PackageStructureTest extends TestCase
{
    public function test_package_tests_directory_does_not_own_a_demo_fixture_tree(): void
    {
        $packageTests = realpath(__DIR__ . '/..');
        self::assertNotFalse($packageTests, 'package tests directory not found');

        // The exact directory shapes that previously held Playground-owned
        // demo behaviour. They must not re-appear inside the package.
        self::assertDirectoryDoesNotExist(
            $packageTests . '/Fixtures/Demo',
            'packages/semitexa-api/tests/Fixtures/ must not contain a Demo/ subtree;'
            . ' demo CRUD/showcase fixtures belong under tests/Playground/RestApi/Fixtures/.'
        );
        self::assertDirectoryDoesNotExist(
            $packageTests . '/Demo',
            'packages/semitexa-api/tests/ must not contain a Demo/ subtree;'
            . ' demo-flavoured tests belong under tests/Playground/RestApi/.'
        );
    }

    public function test_every_test_namespace_in_the_package_starts_with_semitexa_api_tests(): void
    {
        // Package tests own the package, not the host app. A namespace
        // under `App\\Tests\\` or `Semitexa\\Modules\\` inside this
        // directory means a host-app test has been parked here by mistake.
        $packageTests = realpath(__DIR__ . '/..');
        self::assertNotFalse($packageTests);

        $offenders = [];
        foreach ($this->iteratePhp($packageTests) as $file) {
            $contents = (string) file_get_contents($file);
            if (preg_match('/^\s*namespace\s+(?P<ns>[^;]+);/m', $contents, $m) !== 1) {
                continue;
            }
            $namespace = trim($m['ns']);
            if (!str_starts_with($namespace, 'Semitexa\\Api\\Tests')) {
                $offenders[] = $file . ' (namespace ' . $namespace . ')';
            }
        }

        self::assertSame(
            [],
            $offenders,
            "Files under packages/semitexa-api/tests/ must declare a Semitexa\\Api\\Tests\\ namespace.\n"
            . "Host-app tests belong at the repo-root tests/ directory.\n"
            . implode("\n", $offenders),
        );
    }

    /** @return iterable<string> */
    private function iteratePhp(string $root): iterable
    {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS),
        );
        foreach ($iterator as $file) {
            if (!$file instanceof \SplFileInfo) {
                continue;
            }
            if ($file->isDir()) {
                continue;
            }
            if ($file->getExtension() !== 'php') {
                continue;
            }
            yield $file->getPathname();
        }
    }
}
