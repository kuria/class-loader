<?php

namespace Kuria\ClassLoader;

class ClassLoaderTest extends \PHPUnit_Framework_TestCase
{
    public function testRegistration()
    {
        $classLoader = new ClassLoader();

        $this->assertTrue($classLoader->register());
        $this->assertNotFalse(array_search(array($classLoader, 'loadClass'), spl_autoload_functions(), true));

        $this->assertTrue($classLoader->unregister());
        $this->assertFalse(array_search(array($classLoader, 'loadClass'), spl_autoload_functions(), true));
    }

    public function testClassMap()
    {
        $classLoader = new ClassLoader();

        $className = 'Foo\Bar';
        $fileName = 'foo_bar.php';
        $className2 = 'Hello';
        $fileName2 = 'ola.php';

        $classLoader->addClass($className, $fileName);
        $classLoader->addClassMap(array(
            $className2 => $fileName2,
        ));

        $this->assertSame($fileName, $classLoader->findFile($className));
        $this->assertSame($fileName2, $classLoader->findFile($className2));
        $this->assertSame(false, $classLoader->findFile('Unknown\Class'));
    }

    public function testPsr0Prefix()
    {
        $classLoader = new ClassLoader();

        $testDir = __DIR__ . '/fixtures/psr0';

        $classLoader->addPrefixes(array(
            'Plain\\' => $testDir,
            'Underscore_' => $testDir,
            'Combined' => $testDir,
            'Foo' => $testDir,
            'Nonexistent' => $testDir,
        ), ClassLoader::PSR0);

        $this->assertSame($testDir . '/Plain/Lorem.php', $classLoader->findFile('Plain\Lorem'));
        $this->assertSame($testDir . '/Plain/Lorem.php', $classLoader->findFile('\Plain\Lorem'));
        $this->assertSame($testDir . '/Plain/WeMustGoDeeper/Ipsum.php', $classLoader->findFile('Plain\WeMustGoDeeper\Ipsum'));
        $this->assertSame($testDir . '/Underscore/Dolor.php', $classLoader->findFile('Underscore_Dolor'));
        $this->assertSame($testDir . '/Underscore/WeMustGoDeeper/Sit.php', $classLoader->findFile('Underscore_WeMustGoDeeper_Sit'));
        $this->assertSame($testDir . '/Combined/WeMustGoDeeper/Amet.php', $classLoader->findFile('Combined\WeMustGoDeeper_Amet'));
        $this->assertSame($testDir . '/Combined/Underscore_In_Ns/Magnis.php', $classLoader->findFile('Combined\Underscore_In_Ns\Magnis'));
        $this->assertSame($testDir . '/Foo.php', $classLoader->findFile('Foo'));
        $this->assertFalse($classLoader->findFile('Nonexistent'));
    }

    public function testPsr4Prefix()
    {
        $classLoader = new ClassLoader();

        $testDir = __DIR__ . '/fixtures/psr4';

        $classLoader->addPrefix('Plain\\', $testDir . '/plain');
        $classLoader->addPrefix('Under_Scored\\', $testDir . '/underscored');
        $classLoader->addPrefix('Nonexistent\\', $testDir . '/nonexistent');

        $this->assertSame($testDir . '/plain/Lorem.php', $classLoader->findFile('Plain\Lorem'));
        $this->assertSame($testDir . '/plain/Lorem.php', $classLoader->findFile('\Plain\Lorem'));
        $this->assertSame($testDir . '/plain/WeMustGoDeeper/Ipsum.php', $classLoader->findFile('Plain\WeMustGoDeeper\Ipsum'));
        $this->assertSame($testDir . '/underscored/Dolor.php', $classLoader->findFile('Under_Scored\Dolor'));
        $this->assertSame($testDir . '/underscored/WeMustGoDeeper_Sit.php', $classLoader->findFile('Under_Scored\WeMustGoDeeper_Sit'));
        $this->assertSame($testDir . '/underscored/WeMustGoDeeper2/Amet_Magnis.php', $classLoader->findFile('Under_Scored\WeMustGoDeeper2\Amet_Magnis'));
        $this->assertFalse($classLoader->findFile('Nonexistent\\Foo'));
    }

    public function testPsr4IncompatibleClassesDoNotMatchPsr4Prefixes()
    {
        $classLoader = new ClassLoader();

        $testDir = __DIR__ . '/fixtures/psr4';

        $classLoader->addPrefix('X\\', $testDir . '/x');

        $this->assertFalse($classLoader->findFile('X'));
    }

    /**
     * @expectedException        InvalidArgumentException
     * @expectedExceptionMessage PSR-4 prefixes must contain a top level namespace
     */
    public function testExceptionOnInvalidPsr4Prefix1()
    {
        $classLoader = new ClassLoader();

        $classLoader->addPrefix('Foo', 'does_not_matter');
    }

    /**
     * @expectedException        InvalidArgumentException
     * @expectedExceptionMessage PSR-4 prefixes must end with a namespace separator
     */
    public function testExceptionOnInvalidPsr4Prefix2()
    {
        $classLoader = new ClassLoader();

        $classLoader->addPrefix('Foo\Bar', 'does_not_matter');
    }

    /**
     * @expectedException        InvalidArgumentException
     * @expectedExceptionMessage The prefix must not be empty
     */
    public function testExceptionOnEmptyPrefix()
    {
        $classLoader = new ClassLoader();

        $classLoader->addPrefix('', 'foo_bar');
    }

    public function testFallback()
    {
        $classLoader = new ClassLoader();

        $fixtureDir = __DIR__ . '/fixtures';
        $testDirPsr0 = $fixtureDir . '/psr0';
        $testDirPsr4 = $fixtureDir . '/psr4';
        $nonExistentDir = $fixtureDir . '/non-existent';

        $classLoader->addPrefix('Plain\\', array($nonExistentDir, $testDirPsr4 . '/plain'));
        $classLoader->addPrefix('Underscore_', array($nonExistentDir, $testDirPsr0), ClassLoader::PSR0);

        $this->assertSame($testDirPsr4 . '/plain/Lorem.php', $classLoader->findFile('Plain\Lorem'));
        $this->assertSame($testDirPsr0 . '/Underscore/Dolor.php', $classLoader->findFile('Underscore_Dolor'));
    }

    /**
     * @expectedException        RuntimeException
     * @expectedExceptionMessage "Plain\Invalid" was not found
     */
    public function testDebugEnabled()
    {
        $classLoader = new ClassLoader();
        $classLoader->setDebug(true);

        $testDir = __DIR__ . '/fixtures/psr4';

        $classLoader->addPrefix('Plain\\', $testDir . '/plain');

        $classLoader->loadClass('Plain\Invalid');
    }

    public function testDebugDisabled()
    {
        $classLoader = new ClassLoader();
        $classLoader->setDebug(false);

        $testDir = __DIR__ . '/fixtures/psr4';

        $classLoader->addPrefix('Plain\\', $testDir . '/plain');

        $classLoader->loadClass('Plain\Invalid2');
    }
}
