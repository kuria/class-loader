<?php

namespace Kuria\ClassLoader;

/**
 * Class loader (PSR-0, PSR-4)
 *
 * @author ShiraNai7 <shira.cz>
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
    protected $fileSuffixes = array('.php');
    /** @var array class name => path / false */
    protected $classMap = array();
    /** @var array index => array(array(0 => type, 1 => prefix, 2 => prefix_len, 3 => paths), ...) */
    protected $prefixes = array();
    /** @var array type => paths */
    protected $fallbacks = array();

    /**
     * @param bool $debug
     * @param bool $usePrefixes
     */
    public function __construct($debug = false, $usePrefixes = true)
    {
        $this->debug = $debug;
        $this->usePrefixes = $usePrefixes;
        
        if (defined('HHVM_VERSION')) {
            $this->fileSuffixes[] = '.hh';
        }
    }

    /**
     * Register the class loader
     *
     * @param bool $prepend
     * @return bool
     */
    public function register($prepend = false)
    {
        return spl_autoload_register(array($this, 'loadClass'), true, $prepend);
    }

    /**
     * Unregister the class loader
     *
     * @return bool
     */
    public function unregister()
    {
        return spl_autoload_unregister(array($this, 'loadClass'));
    }

    /**
     * @param bool $debug
     * @return static
     */
    public function setDebug($debug)
    {
        $this->debug = $debug;

        return $this;
    }

    /**
     * Set whether prefixes should be searched when locating a class file
     *
     * If prefix usage is disabled, only the class map is searched.
     *
     * @param bool $usePrefixes
     * @return static
     */
    public function setUsePrefixes($usePrefixes)
    {
        $this->usePrefixes = $usePrefixes;

        return $this;
    }

    /**
     * Get file suffixes
     *
     * @return string[]
     */
    public function getFileSuffixes()
    {
        return $this->fileSuffixes;
    }

    /**
     * Add file suffix
     *
     * @param string $fileSuffix
     * @return static
     */
    public function addFileSuffix($fileSuffix)
    {
        if (array_search($fileSuffix, $this->fileSuffixes, true) === false) {
            $this->fileSuffixes[] = $fileSuffix;
        }

        return $this;
    }

    /**
     * Set file suffixes
     *
     * This replaces any previously set suffixes.
     *
     * @param string[] $fileSuffixes
     * @return static
     */
    public function setFileSuffixes(array $fileSuffixes)
    {
        $this->fileSuffixes = $fileSuffixes;

        return $this;
    }

    /**
     * Load the given class
     *
     * @param string $className
     */
    public function loadClass($className)
    {
        if (($fileName = $this->findFile($className)) !== (false)) {
            include $fileName;

            // debug check
            if (
                $this->debug
                && !class_exists($className, false)
                && !interface_exists($className, false)
                && (
                    PHP_VERSION_ID < 50400
                    || !trait_exists($className, false)
                )
            ) {
                throw new \RuntimeException(sprintf(
                    'Class, interface or trait "%s" was not found in file "%s" - possible typo in the name or namespace',
                    $className,
                    $fileName
                ));
            }
        }
    }

    /**
     * Register a single class
     *
     * @param string $class
     * @param string $fileName
     * @return static
     */
    public function addClass($class, $fileName)
    {
        $this->classMap[$class] = $fileName;

        return $this;
    }

    /**
     * Register class map
     *
     * @param array $classMap class => file name
     * @return static
     */
    public function addClassMap(array $classMap)
    {
        $this->classMap = $classMap + $this->classMap;

        return $this;
    }

    /**
     * Register a prefix
     *
     * Adding an empty prefix will register a fallback.
     *
     * @param string          $prefix class name prefix, should end with \ if it is a namespace
     * @param string|string[] $paths  one or more paths, without trailing slash
     * @param int             $type   see ClassLoader::PSR* constants
     * @throws \UnexpectedValueException if an invalid type is given
     * @throws \InvalidArgumentException if an invalid prefix is given
     * @return static
     */
    public function addPrefix($prefix, $paths, $type = self::PSR4)
    {
        if (static::PSR4 !== $type && static::PSR0 !== $type) {
            throw new \UnexpectedValueException('Invalid prefix type');
        }

        if ($prefix === '') {
            foreach ((array) $paths as $path) {
                $this->fallbacks[$type][] = $path;
            }

            return $this;
        }

        $firstNsSep = strpos($prefix, '\\');
        $prefixLength = strlen($prefix);

        // determine index
        if ($firstNsSep === false) {
            // no namespace
            if (static::PSR4 === $type) {
                throw new \InvalidArgumentException(sprintf(
                    'PSR-4 prefixes must contain a top level namespace (got "%s")',
                    $prefix
                ));
            }

            // use first character
            $index = $prefix[0];
        } else {
            // has namespace
            if (static::PSR4 === $type && $prefix[$prefixLength - 1] !== '\\') {
                throw new \InvalidArgumentException(sprintf(
                    'PSR-4 prefixes must end with a namespace separator (got "%s")',
                    $prefix
                ));
            }

            // use vendor namespace
            $index = substr($prefix, 0, $firstNsSep);
        }

        // register
        $this->prefixes[$index][] = array($type, $prefix, $prefixLength, (array) $paths);

        return $this;
    }

    /**
     * Register array of prefixes
     *
     * @param array $prefixes array prefix => path(s)
     * @param int   $type     see ClassLoader::PSR* constants
     * @return static
     */
    public function addPrefixes(array $prefixes, $type = self::PSR4)
    {
        foreach ($prefixes as $prefix => $paths) {
            $this->addPrefix($prefix, $paths, $type);
        }

        return $this;
    }

    /**
     * Find a file for the given class name
     *
     * @param string $className
     * @return string|bool path to the class or false on failure
     */
    public function findFile($className)
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
                        ($isPsr4 = (static::PSR4 === $prefix[0])) && !$isPsr4Compatible
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
                        if ($filePath = $this->findFileWithKnownSuffix($path . $subpath)) {
                            return $filePath;
                        }
                    }
                }
            }

            // scan fallbacks
            if (isset($this->fallbacks[static::PSR0])) {
                $subpath = $subpathPsr0 ?: $this->buildPsr0Subpath($className, $firstNsSep);

                foreach ($this->fallbacks[static::PSR0] as $fallback) {
                    if ($filePath = $this->findFileWithKnownSuffix($fallback . $subpath)) {
                        return $filePath;
                    }
                }
            }

            if (isset($this->fallbacks[static::PSR4])) {
                $subpath = $this->buildPsr4Subpath($className, 0);

                foreach ($this->fallbacks[static::PSR4] as $fallback) {
                    if ($filePath = $this->findFileWithKnownSuffix($fallback . $subpath)) {
                        return $filePath;
                    }
                }
            }

            // cache failed lookup
            $this->classMap[$className] = false;
        }

        return false;
    }

    /**
     * @param string   $className
     * @param int|bool $firstNsSep position of first namespace separator or FALSE
     * @return string
     */
    protected function buildPsr0Subpath($className, $firstNsSep)
    {
        $subpath = '/';

        if ($firstNsSep !== false) {
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

    /**
     * @param string $className
     * @param int $prefixLength
     * @return string
     */
    protected function buildPsr4Subpath($className, $prefixLength)
    {
        return '/' . strtr($prefixLength > 0 ? substr($className, $prefixLength) : $className, '\\', '/');
    }

    /**
     * Try locating the given path using all registered suffixes
     *
     * @param string $pathWithoutSuffix
     * @return string|bool false on failure
     */
    protected function findFileWithKnownSuffix($pathWithoutSuffix)
    {
        foreach ($this->fileSuffixes as $fileSuffix) {
            if (is_file($filePath = $pathWithoutSuffix . $fileSuffix)) {
                return $filePath;
            }
        }

        return false;
    }
}
