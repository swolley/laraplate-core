<?php

declare(strict_types=1);

namespace Modules\Core\Import\Support;

use FilesystemIterator;
use InvalidArgumentException;
use Modules\Core\Import\Contracts\BulkImporterInterface;
use Modules\Core\Import\Contracts\ImportPluginDiscoveryInterface;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ReflectionClass;
use SplFileInfo;

final readonly class FilesystemImportPluginDiscovery implements ImportPluginDiscoveryInterface
{
    /**
     * @param  class-string<BulkImporterInterface>  $contract
     */
    public function __construct(
        private string $label,
        private string $defaultRoot,
        private string $contract = BulkImporterInterface::class,
    ) {
        if (! is_a($this->contract, BulkImporterInterface::class, true)) {
            throw new InvalidArgumentException(
                "Importer contract [{$this->contract}] must extend ".BulkImporterInterface::class.'.',
            );
        }
    }

    public function label(): string
    {
        return $this->label;
    }

    public function root(): ?string
    {
        return is_dir($this->defaultRoot) ? $this->defaultRoot : null;
    }

    public function autoloadPath(?string $root = null): ?string
    {
        $root ??= $this->root();

        if ($root === null) {
            return null;
        }

        $autoload = $root.'/vendor/autoload.php';

        return is_file($autoload) ? $autoload : null;
    }

    public function discoverImplementations(?string $root = null): array
    {
        $root ??= $this->root();

        if ($root === null || ! is_dir($root.'/src')) {
            return [];
        }

        $found = [];

        foreach ($this->phpFiles($root.'/src') as $file) {
            $fqcn = $this->classNameFromPhpFile($file->getPathname());

            if ($fqcn === null || ! class_exists($fqcn)) {
                continue;
            }

            $reflection = new ReflectionClass($fqcn);

            if ($reflection->isInstantiable() && $reflection->implementsInterface($this->contract)) {
                $found[] = $fqcn;
            }
        }

        sort($found);

        return array_values(array_unique($found));
    }

    /**
     * @return iterable<SplFileInfo>
     */
    private function phpFiles(string $source_root): iterable
    {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($source_root, FilesystemIterator::SKIP_DOTS),
        );

        foreach ($iterator as $file) {
            if ($file instanceof SplFileInfo && $file->isFile() && $file->getExtension() === 'php') {
                yield $file;
            }
        }
    }

    /**
     * Read only namespace and class declarations; loading remains Composer's responsibility.
     *
     * @return class-string|null
     */
    private function classNameFromPhpFile(string $path): ?string
    {
        $contents = file_get_contents($path);

        if ($contents === false) {
            return null;
        }

        $namespace = null;

        if (preg_match('/^\s*namespace\s+([^;]+);/m', $contents, $matches) === 1) {
            $namespace = mb_trim($matches[1]);
        }

        if (preg_match('/^\s*(?:final\s+|abstract\s+|readonly\s+)*class\s+(\w+)/m', $contents, $matches) !== 1) {
            return null;
        }

        $class = $matches[1];

        return $namespace === null || $namespace === '' ? $class : $namespace.'\\'.$class;
    }
}
