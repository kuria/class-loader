<?php

namespace Kuria\ClassLoader;

/**
 * Composer bridge
 *
 * @author ShiraNai7 <shira.cz>
 */
class ComposerBridge
{
    /**
     * This is a static class
     */
    private function __construct()
    {
    }

    /**
     * Add autoload configuration of packages managed by Composer
     *
     * @param ClassLoader $classLoader   class loader instance to configure
     * @param string      $vendorDirPath path to the vendor directory without trailing slash
     * @param bool        $usePrefixes   load and enable prefixes 1/0 (disable if using optimized autoload files)
     */
    public static function configure(ClassLoader $classLoader, $vendorDirPath, $usePrefixes = true)
    {
        $composerBasePath = $vendorDirPath . '/composer/';

        $classLoader
            ->addClassMap(require $composerBasePath . 'autoload_classmap.php')
            ->setUsePrefixes($usePrefixes);

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
