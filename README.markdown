Class loader
============

PHP class loader that implements both `PSR-0` and `PSR-4` autoloading:

- https://github.com/php-fig/fig-standards/blob/master/accepted/PSR-0.md
- https://github.com/php-fig/fig-standards/blob/master/accepted/PSR-4-autoloader.md


## Features

- `PSR-0` autoloading
- `PSR-4` autoloading
- class maps
- HHVM support (recognizes `.hh` files)
- composer bridge
- debug mode


## Requirements

- PHP 5.3 or newer


## Compsoser bridge example

The `ComposerBridge` class can be used to initialize autoloading
for packages managed by Composer.

    use Kuria\ClassLoader\ClassLoader;
    use Kuria\ClassLoader\ComposerBridge;

    require __DIR__ . '/vendor/kuria/class-loader/src/ClassLoader.php';
    require __DIR__ . '/vendor/kuria/class-loader/src/ComposerBridge.php';

    $classLoader = new ClassLoader();
    $classLoader->register();

    ComposerBridge::configure($classLoader, __DIR__ . '/vendor');


## Usage example

If you do not use Composer or need to work with the class loader manually,
here is an example.

    use Kuria\ClassLoader\ClassLoader;

    // load the class manually
    require 'path/to/src/ClassLoader.php';

    // create an instance
    $debug = true; // true during development, false in production
    
    $classLoader = new ClassLoader($debug);

    // register the autoloader
    $classLoader->register();

    // add stuff (examples!)
    $classLoader

        // PSR-4 prefix
        ->addPrefix('Foo\\Bar\\', 'vendor/foo/bar/src')

        ->addPrefixes(array(
            'Kuria\\Error\\' => 'vendor/kuria/error/src',
            'Foo\\Baz\\' => 'example/foo/baz',
        ))

        // PSR-0 prefix
        ->addPrefix('Example\\FooBar\\', 'vendor/example/foobar', ClassLoader::PSR0)

        ->addPrefixes(array(
            'Kuria\\Error\\' => 'vendor/kuria/error/src',
            'Foo_' => 'example/foo',
        ), ClassLoader::PSR0)

        // single class
        ->addClass('Foo', 'path/to/foo.class.php')

        // class map
        ->addClassMap(array(
            'Bar' => 'path/to/bar.class.php',
            'Baz' => 'path/to/baz.class.php',
        ))

    ;


## Debug mode

If debug mode is enabled, a class/interface/trait check is performed after
a file is included and an exceptinon is thrown to warn about a potentially
misspelled namespace or class name.

To enable debug mode, call `$classLoader->setDebug(true)` or pass `TRUE`
to the appropriate argument in the constructor.
