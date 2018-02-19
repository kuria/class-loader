<?php declare(strict_types=1);

namespace Kuria\ClassLoader;

/**
 * PSR-0 and PSR-4 class loader implementation
 */
class ClassLoader
{
    const PSR0 = 0;
    const PSR4 = 1;

    /** @var bool */
    protected $debug;
    /** @var bool */
    protected $usePrefixes;
    /** @var string[] */
    protected $fileSuffixes = ['.php'];
    /** @var array class name => path / false */
    protected $classMap = [];
    /** @var array index => array(array(0 => type, 1 => prefix, 2 => prefix_len, 3 => paths), ...) */
    protected $prefixes = [];
    /** @var array type => paths */
    protected $fallbacks = [];

    function __construct(bool $debug = false, bool $usePrefixes = true)
    {
        $this->debug = $debug;
        $this->usePrefixes = $usePrefixes;
        
        if (defined('HHVM_VERSION')) {
            $this->fileSuffixes[] = '.hh';
        }
    }

    /**
     * Register the class loader
     */
    function register(bool $prepend = false): bool
    {
        return spl_autoload_register([$this, 'loadClass'], true, $prepend);
    }

    /**
     * Unregister the class loader
     */
    function unregister(): bool
    {
        return spl_autoload_unregister([$this, 'loadClass']);
    }

    function isDebugEnabled(): bool
    {
        return $this->debug;
    }

    function setDebug(bool $debug): void
    {
        $this->debug = $debug;
    }

    /**
     * Set whether prefixes should be searched when locating a class file
     *
     * If prefix usage is disabled, only the class map is searched.
     */
    function setUsePrefixes(bool $usePrefixes): void
    {
        $this->usePrefixes = $usePrefixes;
    }

    function getFileSuffixes(): array
    {
        return $this->fileSuffixes;
    }

    function addFileSuffix(string $fileSuffix): void
    {
        if (array_search($fileSuffix, $this->fileSuffixes, true) === false) {
            $this->fileSuffixes[] = $fileSuffix;
        }
    }

    /**
     * Set file suffixes
     *
     * This replaces any previously set suffixes.
     */
    function setFileSuffixes(array $fileSuffixes): void
    {
        $this->fileSuffixes = $fileSuffixes;
    }

    function loadClass(string $className): void
    {
        if (($path = $this->findFile($className)) !== null) {
            include $path;

            if ($this->debug) {
                $this->checkLoadedClass($className, $path);
            }
        }
    }

    /**
     * Register a single class
     */
    function addClass(string $class, string $path): void
    {
        $this->classMap[$class] = $path;
    }

    /**
     * Register a class map
     */
    function addClassMap(array $classMap): void
    {
        $this->classMap = $classMap + $this->classMap;
    }

    /**
     * Register a prefix
     *
     * - adding an empty prefix will register a fallback.
     * - see ClassLoader::PSR* constants for available types
     *
     * @throws \UnexpectedValueException if an invalid type is given
     * @throws \InvalidArgumentException if an invalid prefix is given
     */
    function addPrefix(string $prefix, $paths, int $type = self::PSR4): void
    {
        if (static::PSR4 !== $type && static::PSR0 !== $type) {
            throw new \UnexpectedValueException('Invalid prefix type');
        }

        if ($prefix === '') {
            foreach ((array) $paths as $path) {
                $this->fallbacks[$type][] = $path;
            }

            return;
        }

        $firstNsSep = strpos($prefix, '\\');
        $prefixLength = strlen($prefix);

        // determine index
        if ($firstNsSep === false) {
            // no namespace
            if ($type === static::PSR4) {
                throw new \InvalidArgumentException(sprintf(
                    'PSR-4 prefixes must contain a top level namespace (got "%s")',
                    $prefix
                ));
            }

            // use first character
            $index = $prefix[0];
        } else {
            // has namespace
            if ($type === static::PSR4 && $prefix[$prefixLength - 1] !== '\\') {
                throw new \InvalidArgumentException(sprintf(
                    'PSR-4 prefixes must end with a namespace separator (got "%s")',
                    $prefix
                ));
            }

            // use vendor namespace
            $index = substr($prefix, 0, $firstNsSep);
        }

        // register
        $this->prefixes[$index][] = [$type, $prefix, $prefixLength, (array) $paths];
    }

    /**
     * Register a prefix => paths map
     *
     * @see ClassLoader::addPrefix()
     */
    function addPrefixes(array $prefixes, int $type = self::PSR4): void
    {
        foreach ($prefixes as $prefix => $paths) {
            $this->addPrefix($prefix, $paths, $type);
        }
    }

    /**
     * Find a file for the given class name
     *
     * Returns NULL on failure.
     */
    function findFile(string $className): ?string
    {
        if ($className[0] === '\\') {
            $className = substr($className, 1);
        }

        // check class map
        if (isset($this->classMap[$className])) {
            return $this->classMap[$className];
        }

        if ($this->usePrefixes) {
            // determine index
            $firstNsSep = strpos($className, '\\');
            if ($firstNsSep === false) {
                $isPsr4Compatible = false;
                $index = $className[0];
                $firstNsSep = null;
            } else {
                $isPsr4Compatible = true;
                $index = substr($className, 0, $firstNsSep);
            }

            // cache PSR-0 subpath
            $subpathPsr0 = null;

            // scan prefixes
            if (
                isset($this->prefixes[$index])
                || ($isPsr4Compatible && isset($this->prefixes[$index = $className[0]]))
            ) {
                foreach ($this->prefixes[$index] as $prefix) {
                    // 0 => type, 1 => prefix, 2 => prefix_len, 3 => paths
                    if (
                        ($isPsr4 = ($prefix[0] === static::PSR4)) && !$isPsr4Compatible
                        || strncmp($prefix[1], $className, $prefix[2]) !== 0
                    ) {
                        // no match or we are locating a PSR-0 class but the prefix is PSR-4
                        continue;
                    }

                    // compose subpath
                    if ($isPsr4) {
                        // PSR-4 subpath must be built for different prefix lengths
                        $subpath = $this->buildPsr4Subpath($className, $prefix[2]);
                    } else {
                        // PSR-0 subpath contains the entire namespace so it needs to be built only once
                        if ($subpathPsr0 === null) {
                            $subpathPsr0 = $this->buildPsr0Subpath($className, $firstNsSep);
                        }

                        $subpath = $subpathPsr0;
                    }

                    // iterate over possible paths
                    foreach ($prefix[3] as $path) {
                        if (($filePath = $this->findFileWithKnownSuffix($path . $subpath)) !== null) {
                            return $filePath;
                        }
                    }
                }
            }

            // scan fallbacks
            if (isset($this->fallbacks[static::PSR0])) {
                $subpath = $subpathPsr0 ?: $this->buildPsr0Subpath($className, $firstNsSep);

                foreach ($this->fallbacks[static::PSR0] as $fallback) {
                    if (($filePath = $this->findFileWithKnownSuffix($fallback . $subpath)) !== null) {
                        return $filePath;
                    }
                }
            }

            if (isset($this->fallbacks[static::PSR4])) {
                $subpath = $this->buildPsr4Subpath($className, 0);

                foreach ($this->fallbacks[static::PSR4] as $fallback) {
                    if (($filePath = $this->findFileWithKnownSuffix($fallback . $subpath)) !== null) {
                        return $filePath;
                    }
                }
            }

            // cache failed lookup
            $this->classMap[$className] = false;
        }

        return null;
    }

    protected function buildPsr0Subpath(string $className, ?int $firstNsSep): string
    {
        $subpath = '/';

        if ($firstNsSep !== null) {
            $lastNsSep = strrpos($className, '\\');
            $namespace = substr($className, 0, $lastNsSep);
            $plainClassName = substr($className, $lastNsSep + 1);

            $subpath .= strtr($namespace, '\\', '/') . '/';
        } else {
            $plainClassName = $className;
        }

        $subpath .= strtr($plainClassName, '_', '/');

        return $subpath;
    }

    protected function buildPsr4Subpath(string $className, int $prefixLength): string
    {
        return '/' . strtr($prefixLength > 0 ? substr($className, $prefixLength) : $className, '\\', '/');
    }

    /**
     * Try locating the given path using all registered suffixes
     *
     * Returns NULL on failure.
     */
    protected function findFileWithKnownSuffix(string $pathWithoutSuffix): ?string
    {
        foreach ($this->fileSuffixes as $fileSuffix) {
            if (is_file($filePath = $pathWithoutSuffix . $fileSuffix)) {
                return $filePath;
            }
        }

        return null;
    }

    protected function checkLoadedClass(string $className, string $path): void
    {
        // check if the class was actually loaded
        if (
            !class_exists($className, false)
            && !interface_exists($className, false)
            && !trait_exists($className, false)
        ) {
            throw new \RuntimeException(sprintf(
                'Class, interface or trait "%s" was not found in file "%s" - possible typo in the name or namespace',
                $className,
                $path
            ));
        }

        $reflClass = new \ReflectionClass($className);

        // check class name case sensitivity
        if ($className !== $reflClass->name) {
            throw new \RuntimeException(sprintf(
                "Class, interface or trait \"%s\" was loaded as \"%s\" - this will cause issues on case-sensitive filesystems.\n\n"
                    . "Likely causes:\n\n"
                    . " a) wrong class name or namespace specified in \"%s\"\n"
                    . " b) wrong use statement or literal class name used in another PHP file\n"
                    . " c) wrong class name or namespace used in autoload-triggering code such as class_exists() or reflection",
                $reflClass->name,
                $className,
                $path
            ));
        }

        // check file name case sensitivity
        $fileName = basename($path);
        $actualFileName = basename($reflClass->getFileName());

        if ($fileName !== $actualFileName) {
            throw new \RuntimeException(sprintf(
                "Class, interface or trait \"%s\" was loaded from file \"%s\", but the actual file name is \"%s\" - this will cause issues on case-sensitive filesystems.\n\n"
                . "Likely causes:\n\n"
                . " a) wrong file name\n"
                . " b) wrong class name specified in \"%s\"",
                $className,
                $fileName,
                $actualFileName,
                $path
            ));
        }
    }
}
