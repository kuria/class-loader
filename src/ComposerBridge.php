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
     */
    public static function configure(ClassLoader $classLoader, $vendorDirPath)
    {
        $composerBasePath = $vendorDirPath . '/composer/';

        $classLoader->addClassMap(include $composerBasePath . 'autoload_classmap.php');
        $classLoader->addPrefixes(include $composerBasePath . 'autoload_psr4.php');
        $classLoader->addPrefixes(include $composerBasePath . 'autoload_namespaces.php', ClassLoader::PSR0);

        foreach (include $composerBasePath . 'autoload_files.php' as $file) {
            require $file;
        }
    }
}
