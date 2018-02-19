Class loader
############

PHP class loader that implements both `PSR-0 <https://github.com/php-fig/fig-standards/blob/master/accepted/PSR-0.md>`_
and `PSR-4 <https://github.com/php-fig/fig-standards/blob/master/accepted/PSR-4-autoloader.md>`_ autoloading.

.. contents::


Features
********

- PSR-0 and PSR-4 autoloading
- class maps
- custom suffixes
- HHVM support (recognizes ``.hh`` files if used in HHVM)
- composer bridge
- debug mode


Requirements
************

- PHP 7.1.0+


Usage examples
**************

Registering prefixes
====================

.. code:: php

   <?php

   use Kuria\ClassLoader\ClassLoader;

   // load the class loader manually
   require '/path/to/src/ClassLoader.php';

   // create an instance
   $debug = true; // true during development, false in production

   $classLoader = new ClassLoader($debug);

   // register the autoloader
   $classLoader->register();

   // PSR-4 prefix
   $classLoader->addPrefix('Foo\\Bar\\', 'vendor/foo/bar/src');

   $classLoader->addPrefixes([
       'Kuria\\Error\\' => 'vendor/kuria/error/src',
       'Foo\\Baz\\' => 'example/foo/baz',
   ]);

   // PSR-0 prefix
   $classLoader->addPrefix('Example\\FooBar\\', 'vendor/example/foobar', ClassLoader::PSR0);

   $classLoader->addPrefixes([
       'Kuria\\Error\\' => 'vendor/kuria/error/src',
       'Foo_' => 'example/foo',
   ], ClassLoader::PSR0);

   // PSR-4 fallback (empty prefix)
   $classLoader->addPrefix('', 'src');

   // PSR-0 fallback (empty prefix)
   $classLoader->addPrefix('', 'old-code/example', ClassLoader::PSR0);

   // single class
   $classLoader->addClass('Foo', 'path/to/foo.class.php');

   // class map
   $classLoader->addClassMap([
       'Bar' => 'path/to/bar.class.php',
       'Baz' => 'path/to/baz.class.php',
   ]);


Using the composer bridge
=========================

The ``ComposerBridge`` class can be used to initialize autoloading for packages managed by Composer.

.. code:: php

   <?php

   use Kuria\ClassLoader\ClassLoader;
   use Kuria\ClassLoader\ComposerBridge;

   require __DIR__ . '/vendor/kuria/class-loader/src/ClassLoader.php';
   require __DIR__ . '/vendor/kuria/class-loader/src/ComposerBridge.php';

   $classLoader = new ClassLoader();

   ComposerBridge::configure($classLoader, __DIR__ . '/vendor');

   $classLoader->register();


Disabling prefixes
------------------

If you are using an optimized autoloader, you can pass an optional third parameter
to ``configure()`` to disable prefixes completely. Only the class maps and files
will be loaded.

.. code:: php

   <?php

   ComposerBridge::configure($classLoader, __DIR__ . '/vendor', false);


Debug mode
==========

If debug mode is enabled, the following checks are performed after a file is loaded:

- whether the class was actually found in the file

  - detects wrong or misspelled namespaces or class names

- whether the class name matches exactly what is defined in the file

  - detects mismatched character case in namespaces or class names or other class
    name usage, which would cause issues on case-sensitive filesystems

- whether the loaded file name matches the actual file name

  - detects mismatched character case in the file name, which would cause issues
    on case-sensitive filesystems

To enable debug mode, call ``$classLoader->setDebug(true)`` or pass ``true`` to
the appropriate constructor argument.
