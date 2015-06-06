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

        $this->assertSame($testDir . '/Plain/Foo.php', $classLoader->findFile('Plain\Foo'));
        $this->assertSame($testDir . '/Plain/Foo.php', $classLoader->findFile('\Plain\Foo'));
        $this->assertSame($testDir . '/Plain/Deeper/Foo.php', $classLoader->findFile('Plain\Deeper\Foo'));
        $this->assertSame($testDir . '/Underscore/Foo.php', $classLoader->findFile('Underscore_Foo'));
        $this->assertSame($testDir . '/Underscore/Deeper/Foo.php', $classLoader->findFile('Underscore_Deeper_Foo'));
        $this->assertSame($testDir . '/Combined/Deeper/Foo.php', $classLoader->findFile('Combined\Deeper_Foo'));
        $this->assertSame($testDir . '/Combined/Underscore_In_Ns/Foo.php', $classLoader->findFile('Combined\Underscore_In_Ns\Foo'));
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

        $this->assertSame($testDir . '/plain/Foo.php', $classLoader->findFile('Plain\Foo'));
        $this->assertSame($testDir . '/plain/Foo.php', $classLoader->findFile('\Plain\Foo'));
        $this->assertSame($testDir . '/plain/Deeper/Foo.php', $classLoader->findFile('Plain\Deeper\Foo'));
        $this->assertSame($testDir . '/underscored/Foo.php', $classLoader->findFile('Under_Scored\Foo'));
        $this->assertSame($testDir . '/underscored/Deeper_Foo.php', $classLoader->findFile('Under_Scored\Deeper_Foo'));
        $this->assertSame($testDir . '/underscored/Deeper/Foo_Bar.php', $classLoader->findFile('Under_Scored\Deeper\Foo_Bar'));
        $this->assertFalse($classLoader->findFile('Nonexistent\\Foo'));
    }

    public function testPsr4IncompatibleClassesDoNotMatchPsr4Prefixes()
    {
        $classLoader = new ClassLoader();

        $testDir = __DIR__ . '/fixtures/psr4';

        $classLoader->addPrefix('X\\', $testDir . '/incompatible');

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

        $this->assertSame($testDirPsr4 . '/plain/Foo.php', $classLoader->findFile('Plain\Foo'));
        $this->assertSame($testDirPsr0 . '/Underscore/Foo.php', $classLoader->findFile('Underscore_Foo'));
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

    public function testPrefixesDisabled()
    {
        $classLoader = new ClassLoader();
        $classLoader->setUsePrefixes(false);

        $fixtureDir = __DIR__ . '/fixtures';
        $testDirPsr0 = $fixtureDir . '/psr0';
        $testDirPsr4 = $fixtureDir . '/psr4';

        $classLoader->addPrefix('Plain\\', $testDirPsr4 . '/plain');
        $classLoader->addPrefix('Plain\\', $testDirPsr0, ClassLoader::PSR0);
        $this->assertFalse($classLoader->findFile('Plain\Foo'));

        $classLoader->addClass('Plain\Foo', 'test');
        $this->assertSame('test', $classLoader->findFile('Plain\Foo'));
    }

    public function testPrefixesEnabled()
    {
        $classLoader = new ClassLoader();
        $classLoader->setUsePrefixes(true);

        $fixtureDir = __DIR__ . '/fixtures';
        $testDirPsr0 = $fixtureDir . '/psr0';
        $testDirPsr4 = $fixtureDir . '/psr4';

        $classLoader->addPrefix('Plain\\', $testDirPsr4 . '/plain');
        $classLoader->addPrefix('Underscore_', $testDirPsr0, ClassLoader::PSR0);
        $this->assertSame($testDirPsr4 . '/plain/Foo.php', $classLoader->findFile('Plain\Foo'));
        $this->assertSame($testDirPsr0 . '/Underscore/Foo.php', $classLoader->findFile('Underscore_Foo'));

        $classLoader->addClass('Plain\Foo', 'foo');
        $classLoader->addClass('Underscore_Foo', 'bar');
        $this->assertSame('foo', $classLoader->findFile('Plain\Foo'));
        $this->assertSame('bar', $classLoader->findFile('Underscore_Foo'));
    }

    public function testDefaultFileSuffixes()
    {
        $classLoader = new ClassLoader();

        $this->assertSame($this->getDefaultFileSuffixes(), $classLoader->getFileSuffixes());
    }

    public function testConfigureFileSuffixes()
    {
        $classLoader = new ClassLoader();

        $classLoader->addFileSuffix('.custom');
        $this->assertSame(array_merge($this->getDefaultFileSuffixes(), array('.custom')), $classLoader->getFileSuffixes());

        $classLoader->setFileSuffixes(array('.custom'));
        $this->assertSame(array('.custom'), $classLoader->getFileSuffixes());
    }

    public function testCustomFileSuffix()
    {
        $classLoader = new ClassLoader();
        
        $fixtureDir = __DIR__ . '/fixtures';

        $testDirPsr0 = $fixtureDir . '/psr0';
        $testDirPsr4 = $fixtureDir . '/psr4';

        $classLoader->addFileSuffix('.custom');

        $classLoader->addPrefix('Custom\\', $testDirPsr4 . '/custom');
        $classLoader->addPrefix('Custom\\', $testDirPsr0, ClassLoader::PSR0);

        $this->assertSame($testDirPsr4 . '/custom/Foo.custom', $classLoader->findFile('Custom\Foo'));
        $this->assertSame($testDirPsr0 . '/Custom/Bar.custom', $classLoader->findFile('Custom\Bar'));
    }

    private function getDefaultFileSuffixes()
    {
        return defined('HHVM_VERSION')
            ? array('.php', '.hh')
            : array('.php')
        ;
    }
}
