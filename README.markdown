Class loader
============

PHP class loader that implements both `PSR-0` and `PSR-4`:

- https://github.com/php-fig/fig-standards/blob/master/accepted/PSR-0.md
- https://github.com/php-fig/fig-standards/blob/master/accepted/PSR-4-autoloader.md


## Features

- `PSR-0` and `PSR-4` autoloading
- composer bridge
- class maps
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

    // register it as an autoloader
    $classLoader->register();

    // add stuff (examples!)
    $classLoader

        // PSR-4 prefix
        ->addPrefix('Kuria\\Form\\', 'vendor/kuria/form/src')

        ->addPrefixes(array(
            'Kuria\\Error\\' => 'vendor/kuria/error/src',
            'Foo\\Bar\\' => 'example/foo/bar',
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
misspelled namespace / class name.

To enable debug mode, call:

    $classLoader->setDebug(true);
