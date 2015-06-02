<?php

namespace Kuria\ClassLoader;

/**
 * Class loader (PSR-0, PSR-4)
 *
 * @author ShiraNai7 <shira.cz>
 */
class ClassLoader
{
    /** PSR-0 autoloader standard */
    const PSR0 = 0;
    /** PSR-4 autoloader standard */
    const PSR4 = 1;

    /** @var array class name => path / false */
    protected $classMap = array();
    /** @var array index => array(array(0 => standard, 1 => prefix, 2 => prefix_len, 3 => paths), ...) */
    protected $prefixes = array();
    /** @var bool debug mode 1/0 */
    protected $debug = false;

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
     * Load the given class
     *
     * @param string $className
     */
    public function loadClass($className)
    {
        if (false !== ($fileName = $this->findFile($className))) {
            include $fileName;

            // debug check
            if (
                $this->debug
                && !class_exists($className, false)
                && !interface_exists($className, false)
                && (
                    !function_exists('trait_exists')
                    || !trait_exists($className, false)
                )
            ) {
                throw new \RuntimeException(sprintf(
                    'Class/interface/trait "%s" was not found in file "%s", possible typo in namespace or class name?',
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
     * @param string          $prefix   class name prefix, should end with \ if it is a namespace
     * @param string|string[] $paths    one or more paths, without trailing slash
     * @param int             $standard see ClassLoader::PSRx constants
     * @throws \InvalidArgumentException
     * @return static
     */
    public function addPrefix($prefix, $paths, $standard = self::PSR4)
    {
        if ('' === $prefix) {
            throw new \InvalidArgumentException('The prefix must not be empty');
        }

        $firstNsSep = strpos($prefix, '\\');
        $prefixLength = strlen($prefix);

        // determine index
        if (false === $firstNsSep) {
            // no namespace
            if (self::PSR4 === $standard) {
                throw new \InvalidArgumentException(sprintf(
                    'PSR-4 prefixes must contain a top level namespace (got "%s")',
                    $prefix
                ));
            }

            // use first character
            $index = $prefix[0];
        } else {
            // has namespace
            if (self::PSR4 === $standard && '\\' !== $prefix[$prefixLength - 1]) {
                throw new \InvalidArgumentException(sprintf(
                    'PSR-4 prefixes must end with a namespace separator (got "%s")',
                    $prefix
                ));
            }

            // use vendor namespace
            $index = substr($prefix, 0, $firstNsSep);
        }

        // register
        $this->prefixes[$index][] = array($standard, $prefix, $prefixLength, (array) $paths);

        return $this;
    }

    /**
     * Register array of prefixes
     *
     * @param array $prefixes array prefix => path(s)
     * @param int   $standard see ClassLoader::PSRx constants
     * @return static
     */
    public function addPrefixes(array $prefixes, $standard = self::PSR4)
    {
        foreach ($prefixes as $prefix => $paths) {
            $this->addPrefix($prefix, $paths, $standard);
        }

        return $this;
    }

    /**
     * Find file, given a class name
     *
     * @param string $className
     * @return string|bool path to the class or false on failure
     */
    public function findFile($className)
    {
        if ('\\' === $className[0]) {
            $className = substr($className, 1);
        }

        // check class map
        if (isset($this->classMap[$className])) {
            return $this->classMap[$className];
        }

        // determine index
        $firstNsSep = strpos($className, '\\');
        if (false === $firstNsSep) {
            $isPsr4Compatible = false;
            $index = $className[0];
        } else {
            $isPsr4Compatible = true;
            $index = substr($className, 0, $firstNsSep);
        }

        // scan prefixes
        if (
            isset($this->prefixes[$index])
            || ($isPsr4Compatible && isset($this->prefixes[$index = $className[0]]))
        ) {
            $subpathPsr0 = null; // lazy

            foreach ($this->prefixes[$index] as $prefix) {
                // 0 => standard, 1 => prefix, 2 => prefix_len, 3 => paths
                if (
                    ($isPsr4 = (self::PSR4 === $prefix[0])) && !$isPsr4Compatible
                    || 0 !== strncmp($prefix[1], $className, $prefix[2])
                ) {
                    // no match or we are locating a PSR-0 class
                    // but the prefix uses the PSR-4 standard
                    continue;
                }

                // compose subpath
                if ($isPsr4) {
                    // PSR-4 prefix is not included in the subpath
                    $subpath = '/' . str_replace('\\', '/', substr($className, $prefix[2])) . '.php';
                } else {
                    // PSR-0 prefix is included in the subpath
                    // but the subpath needs to be composed just once
                    if (null === $subpathPsr0) {
                        if (false !== $firstNsSep) {
                            $lastNsSep = strrpos($className, '\\');
                            $namespace = substr($className, 0, $lastNsSep);
                            $plainClassName = substr($className, $lastNsSep + 1);
                        } else {
                            $namespace = null;
                            $plainClassName = $className;
                        }

                        $subpathPsr0 = '/';
                        if (null !== $namespace) {
                            $subpathPsr0 .= str_replace('\\', '/', $namespace) . '/';
                        }
                        $subpathPsr0 .= str_replace('_', '/', $plainClassName) . '.php';
                    }

                    $subpath = $subpathPsr0;
                }

                // iterate over possible paths
                foreach ($prefix[3] as $path) {
                    if (is_file($filePath = $path . $subpath)) {
                        // match
                        return $filePath;
                    }
                }
            }

            // cache failed lookup
            $this->classMap[$className] = false;
        }

        return false;
    }
}
