<?php declare(strict_types=1);

namespace Kuria\ClassLoader;

abstract class ComposerBridge
{
    /**
     * Add autoload configuration of packages managed by Composer
     *
     * - $vendorDirPath should be a path to the vendor directory without a trailing slash
     * - if $usePrefixes is FALSE, prefixes will not be loaded (use with optimized autoload files)
     */
    static function configure(ClassLoader $classLoader, string $vendorDirPath, bool $usePrefixes = true): void
    {
        $composerBasePath = $vendorDirPath . '/composer/';

        $classLoader->addClassMap(require $composerBasePath . 'autoload_classmap.php');
        $classLoader->setUsePrefixes($usePrefixes);

        if ($usePrefixes) {
            $classLoader->addPrefixes(require $composerBasePath . 'autoload_psr4.php');
            $classLoader->addPrefixes(require $composerBasePath . 'autoload_namespaces.php', ClassLoader::PSR0);
        }

        $autoloadedFilesPath =  $composerBasePath . 'autoload_files.php';

        if (is_file($autoloadedFilesPath)) {
            foreach (require $autoloadedFilesPath as $file) {
                require $file;
            }
        }
    }
}
