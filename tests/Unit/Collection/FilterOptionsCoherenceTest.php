<?php

declare(strict_types=1);

namespace Semitexa\Api\Tests\Unit\Collection;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use Semitexa\Api\Attribute\CollectionFilterable;
use Semitexa\Api\Attribute\CollectionFilterOptions;
use Semitexa\Core\Support\ProjectRoot;

/**
 * A #[CollectionFilterOptions] field that is not in the response's
 * #[CollectionFilterable] allowlist, caught before anything serves it.
 *
 * CollectionContractBlockContributor throws a LogicException on that
 * mismatch — correctly, it is a programming error — but it throws during
 * contract ASSEMBLY, which happens when something asks: an OPTIONS metadata
 * probe, the OpenAPI generator, the GraphQL registry. So a misdeclared route
 * shipped green and answered 500 to the first client that probed it, which is
 * the wrong end of the release to find out.
 *
 * Every comparable coherence rule in this codebase is enforced ahead of a
 * request — the PHPStan rules under semitexa-core, the module-structure
 * validator, the ratchets under semitexa-dev. This is the same thing for this
 * rule: a repo-wide sweep that fails CI instead of a request. The exception
 * stays as the last line of defence.
 */
final class FilterOptionsCoherenceTest extends TestCase
{
    /**
     * Production source only. tests/Fixtures/Collection/BadFilterOptionsCollectionResponse
     * is misdeclared ON PURPOSE — it is what proves the runtime exception still
     * fires — so sweeping the fixtures would fail on the fixture that exists to
     * fail.
     */
    private const ROOTS = ['packages/*/src', 'vendor/semitexa/*/src', 'src/modules/*/src'];

    private int $scannedFiles = 0;

    #[Test]
    public function every_declared_filter_option_is_inside_its_filter_allowlist(): void
    {
        $offenders = [];
        $checked = 0;

        foreach ($this->responsesDeclaringFilterOptions() as $class) {
            $checked++;
            $reflection = new ReflectionClass($class);

            $optionAttrs = $reflection->getAttributes(CollectionFilterOptions::class);
            if ($optionAttrs === []) {
                continue;
            }
            /** @var CollectionFilterOptions $options */
            $options = $optionAttrs[0]->newInstance();

            $allowed = [];
            foreach ($reflection->getAttributes(CollectionFilterable::class) as $filterable) {
                /** @var CollectionFilterable $instance */
                $instance = $filterable->newInstance();
                $allowed = array_keys($instance->fields);
            }

            foreach ($options->fields as $field) {
                if (!in_array($field, $allowed, true)) {
                    $offenders[] = sprintf(
                        '%s: filter option "%s" is not in #[CollectionFilterable] (%s)',
                        $class,
                        $field,
                        $allowed === [] ? 'no allowlist declared' : implode(', ', $allowed),
                    );
                }
            }
        }

        sort($offenders);

        self::assertSame(
            [],
            $offenders,
            "A filter option outside the filter allowlist throws at contract assembly — an OPTIONS\n"
            . "probe, the OpenAPI dump or the GraphQL registry, whichever asks first:\n  - "
            . implode("\n  - ", $offenders),
        );

        // A scan that stops finding classes passes exactly like a clean
        // repository. Four production responses in the monorepo declare the
        // attribute today; an installed app may legitimately have none, so
        // there the guard is that the sweep read source at all.
        self::assertGreaterThan(0, $this->scannedFiles, 'the sweep read no PHP source — its roots no longer match the layout');
        if (is_dir(ProjectRoot::get() . '/packages')) {
            self::assertGreaterThanOrEqual(
                4,
                $checked,
                'the sweep found almost no responses — it is no longer reading production source',
            );
        }
    }

    /**
     * @return list<class-string> production classes carrying #[CollectionFilterOptions]
     */
    private function responsesDeclaringFilterOptions(): array
    {
        // The project root, not the package: the package sits at
        // packages/semitexa-api in the monorepo and vendor/semitexa/api in an app.
        $root = ProjectRoot::get();
        $found = [];

        foreach (self::ROOTS as $pattern) {
            foreach (glob($root . '/' . $pattern, GLOB_ONLYDIR) ?: [] as $dir) {
                foreach ($this->phpFilesIn($dir) as $file) {
                    $source = @file_get_contents($file);
                    if ($source === false) {
                        self::fail('the sweep could not read ' . $file . ' — an unreadable file is not a cleared file');
                    }
                    $this->scannedFiles++;
                    if (!str_contains($source, '#[CollectionFilterOptions')) {
                        continue;
                    }

                    $class = $this->classNameIn($source);
                    if ($class !== null && class_exists($class)) {
                        $found[] = $class;
                    }
                }
            }
        }

        // A monorepo can expose the same package under packages/ and vendor/.
        $found = array_values(array_unique($found));
        sort($found);

        return $found;
    }

    /** @return list<string> */
    private function phpFilesIn(string $dir): array
    {
        $out = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
        );
        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getExtension() === 'php') {
                $out[] = $file->getPathname();
            }
        }

        return $out;
    }

    private function classNameIn(string $source): ?string
    {
        if (preg_match('/^namespace\s+([^;]+);/m', $source, $ns) !== 1) {
            return null;
        }
        if (preg_match('/^(?:final\s+|abstract\s+|readonly\s+)*class\s+(\w+)/m', $source, $cls) !== 1) {
            return null;
        }

        return trim($ns[1]) . '\\' . $cls[1];
    }
}
