<?php

namespace Kuria\ClassLoader;

class ClassLoaderTest extends \PHPUnit_Framework_TestCase
{
    /** @var ClassLoader */
    private $classLoader;

    protected function setUp()
    {
        $this->classLoader = new ClassLoader();
    }

    public function testRegistration()
    {
        $this->assertTrue($this->classLoader->register());
        $this->assertNotFalse(array_search(array($this->classLoader, 'loadClass'), spl_autoload_functions(), true));

        $this->assertTrue($this->classLoader->unregister());
        $this->assertFalse(array_search(array($this->classLoader, 'loadClass'), spl_autoload_functions(), true));
    }

    public function testClassMap()
    {
        $className = 'Foo\Bar';
        $fileName = 'foo_bar.php';
        $className2 = 'Hello';
        $fileName2 = 'ola.php';

        $this->classLoader->addClass($className, $fileName);
        $this->classLoader->addClassMap(array(
            $className2 => $fileName2,
        ));

        $this->assertSame($fileName, $this->classLoader->findFile($className));
        $this->assertSame($fileName2, $this->classLoader->findFile($className2));
        $this->assertSame(false, $this->classLoader->findFile('Unknown\Class'));
    }

    public function testPsr0Prefix()
    {
        $testDir = __DIR__ . '/fixtures/psr0';

        $this->classLoader->addPrefixes(array(
            'Plain\\' => $testDir,
            'Underscore_' => $testDir,
            'Combined' => $testDir,
            'Foo' => $testDir,
            'Nonexistent' => $testDir,
        ), ClassLoader::PSR0);

        $this->assertSame($testDir . '/Plain/Foo.php', $this->classLoader->findFile('Plain\Foo'));
        $this->assertSame($testDir . '/Plain/Foo.php', $this->classLoader->findFile('\Plain\Foo'));
        $this->assertSame($testDir . '/Plain/Deeper/Bar.php', $this->classLoader->findFile('Plain\Deeper\Bar'));
        $this->assertSame($testDir . '/Underscore/Foo.php', $this->classLoader->findFile('Underscore_Foo'));
        $this->assertSame($testDir . '/Underscore/Deeper/Foo.php', $this->classLoader->findFile('Underscore_Deeper_Foo'));
        $this->assertSame($testDir . '/Combined/Deeper/Foo.php', $this->classLoader->findFile('Combined\Deeper_Foo'));
        $this->assertSame($testDir . '/Combined/Underscore_In_Ns/Foo.php', $this->classLoader->findFile('Combined\Underscore_In_Ns\Foo'));
        $this->assertSame($testDir . '/Foo.php', $this->classLoader->findFile('Foo'));
        $this->assertFalse($this->classLoader->findFile('Nonexistent'));
    }

    public function testPsr4Prefix()
    {
        $testDir = __DIR__ . '/fixtures/psr4';

        $this->classLoader->addPrefix('Plain\\', $testDir . '/plain');
        $this->classLoader->addPrefix('Under_Scored\\', $testDir . '/underscored');
        $this->classLoader->addPrefix('Nonexistent\\', $testDir . '/nonexistent');

        $this->assertSame($testDir . '/plain/FooBar.php', $this->classLoader->findFile('Plain\FooBar'));
        $this->assertSame($testDir . '/plain/FooBar.php', $this->classLoader->findFile('\Plain\FooBar'));
        $this->assertSame($testDir . '/plain/Deeper/Baz.php', $this->classLoader->findFile('Plain\Deeper\Baz'));
        $this->assertSame($testDir . '/underscored/Foo.php', $this->classLoader->findFile('Under_Scored\Foo'));
        $this->assertSame($testDir . '/underscored/Deeper_Foo.php', $this->classLoader->findFile('Under_Scored\Deeper_Foo'));
        $this->assertSame($testDir . '/underscored/Deeper/Foo_Bar.php', $this->classLoader->findFile('Under_Scored\Deeper\Foo_Bar'));
        $this->assertFalse($this->classLoader->findFile('Nonexistent\\Foo'));
    }

    public function testPsr4IncompatibleClassesDoNotMatchPsr4Prefixes()
    {
        $testDir = __DIR__ . '/fixtures/psr4';

        $this->classLoader->addPrefix('X\\', $testDir . '/incompatible');

        $this->assertFalse(
            $this->classLoader->findFile('X') // X is not in a namespace
        );
    }

    public function testPsr0Fallback()
    {
        $testDir = __DIR__ . '/fixtures/psr0';

        $this->classLoader->addPrefix('', $testDir, ClassLoader::PSR0);

        $this->assertSame($testDir . '/Foo.php', $this->classLoader->findFile('Foo'));
        $this->assertSame($testDir . '/Combined/Deeper/Foo.php', $this->classLoader->findFile('Combined\Deeper_Foo'));

    }

    public function testPsr4Fallback()
    {
        $testDir = __DIR__ . '/fixtures/psr4';

        $this->classLoader->addPrefix('', array($testDir . '/fallback'));
        $this->classLoader->addPrefix('', array($testDir . '/fallback2'));

        $this->assertSame($testDir . '/fallback/Foo/Deeper/Bar.php', $this->classLoader->findFile('Foo\Deeper\Bar'));
        $this->assertSame($testDir . '/fallback2/Lorem/Baz.php', $this->classLoader->findFile('Lorem\Baz'));
    }

    /**
     * @expectedException        UnexpectedValueException
     * @expectedExceptionMessage Invalid prefix type
     */
    public function testExceptionOnInvalidType()
    {
        $this->classLoader->addPrefix('Foo', 'does_not_matter', -150);
    }

    /**
     * @expectedException        InvalidArgumentException
     * @expectedExceptionMessage PSR-4 prefixes must contain a top level namespace
     */
    public function testExceptionOnInvalidPsr4Prefix1()
    {
        $this->classLoader->addPrefix('Foo', 'does_not_matter');
    }

    /**
     * @expectedException        InvalidArgumentException
     * @expectedExceptionMessage PSR-4 prefixes must end with a namespace separator
     */
    public function testExceptionOnInvalidPsr4Prefix2()
    {
        $this->classLoader->addPrefix('Foo\Bar', 'does_not_matter');
    }

    public function testSkipInvalidPaths()
    {
        $fixtureDir = __DIR__ . '/fixtures';
        $testDirPsr0 = $fixtureDir . '/psr0';
        $testDirPsr4 = $fixtureDir . '/psr4';
        $nonExistentDir = $fixtureDir . '/non-existent';

        $this->classLoader->addPrefix('Plain\\', array($nonExistentDir, $testDirPsr4 . '/plain'));
        $this->classLoader->addPrefix('Underscore_', array($nonExistentDir, $testDirPsr0), ClassLoader::PSR0);

        $this->assertSame($testDirPsr4 . '/plain/FooBar.php', $this->classLoader->findFile('Plain\FooBar'));
        $this->assertSame($testDirPsr0 . '/Underscore/Foo.php', $this->classLoader->findFile('Underscore_Foo'));
    }

    /**
     * @expectedException        RuntimeException
     * @expectedExceptionMessage "Plain\Invalid" was not found
     */
    public function testDebugEnabled()
    {
        $this->classLoader->setDebug(true);

        $testDir = __DIR__ . '/fixtures/psr4';

        $this->classLoader->addPrefix('Plain\\', $testDir . '/plain');
        $this->classLoader->loadClass('Plain\Invalid');
    }

    public function testDebugDisabled()
    {
        $this->classLoader->setDebug(false);

        $testDir = __DIR__ . '/fixtures/psr4';

        $this->classLoader->addPrefix('Plain\\', $testDir . '/plain');
        $this->classLoader->loadClass('Plain\Invalid2');
    }

    public function testPrefixesDisabled()
    {
        $this->classLoader->setUsePrefixes(false);

        $fixtureDir = __DIR__ . '/fixtures';
        $testDirPsr0 = $fixtureDir . '/psr0';
        $testDirPsr4 = $fixtureDir . '/psr4';

        $this->classLoader->addPrefix('Plain\\', $testDirPsr4 . '/plain');
        $this->classLoader->addPrefix('Plain\\', $testDirPsr0, ClassLoader::PSR0);
        $this->assertFalse($this->classLoader->findFile('Plain\Foo'));

        $this->classLoader->addClass('Plain\Foo', 'test');
        $this->assertSame('test', $this->classLoader->findFile('Plain\Foo'));
    }

    public function testPrefixesEnabled()
    {
        $this->classLoader->setUsePrefixes(true);

        $fixtureDir = __DIR__ . '/fixtures';
        $testDirPsr0 = $fixtureDir . '/psr0';
        $testDirPsr4 = $fixtureDir . '/psr4';

        $this->classLoader->addPrefix('Plain\\', $testDirPsr4 . '/plain');
        $this->classLoader->addPrefix('Underscore_', $testDirPsr0, ClassLoader::PSR0);
        $this->assertSame($testDirPsr4 . '/plain/FooBar.php', $this->classLoader->findFile('Plain\FooBar'));
        $this->assertSame($testDirPsr0 . '/Underscore/Foo.php', $this->classLoader->findFile('Underscore_Foo'));

        $this->classLoader->addClass('Plain\Foo', 'foo');
        $this->classLoader->addClass('Underscore_Foo', 'bar');
        $this->assertSame('foo', $this->classLoader->findFile('Plain\Foo'));
        $this->assertSame('bar', $this->classLoader->findFile('Underscore_Foo'));
    }

    public function testDefaultFileSuffixes()
    {
        $this->assertSame($this->getDefaultFileSuffixes(), $this->classLoader->getFileSuffixes());
    }

    public function testConfigureFileSuffixes()
    {
        $this->classLoader->addFileSuffix('.custom');
        $this->assertSame(array_merge($this->getDefaultFileSuffixes(), array('.custom')), $this->classLoader->getFileSuffixes());

        $this->classLoader->setFileSuffixes(array('.custom'));
        $this->assertSame(array('.custom'), $this->classLoader->getFileSuffixes());
    }

    public function testCustomFileSuffix()
    {
        $fixtureDir = __DIR__ . '/fixtures';

        $testDirPsr0 = $fixtureDir . '/psr0';
        $testDirPsr4 = $fixtureDir . '/psr4';

        $this->classLoader->addFileSuffix('.custom');

        $this->classLoader->addPrefix('Custom\\', $testDirPsr4 . '/custom');
        $this->classLoader->addPrefix('Custom\\', $testDirPsr0, ClassLoader::PSR0);

        $this->assertSame($testDirPsr4 . '/custom/Foo.custom', $this->classLoader->findFile('Custom\Foo'));
        $this->assertSame($testDirPsr0 . '/Custom/Bar.custom', $this->classLoader->findFile('Custom\Bar'));
    }

    private function getDefaultFileSuffixes()
    {
        return defined('HHVM_VERSION')
            ? array('.php', '.hh')
            : array('.php');
    }
}
