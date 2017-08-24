<?php declare(strict_types=1);

namespace Kuria\ClassLoader;

use PHPUnit\Framework\TestCase;

class ClassLoaderTest extends TestCase
{
    /** @var ClassLoader */
    private $classLoader;

    protected function setUp()
    {
        $this->classLoader = new ClassLoader();
    }

    function testRegistration()
    {
        $this->assertTrue($this->classLoader->register());
        $this->assertNotFalse(array_search([$this->classLoader, 'loadClass'], spl_autoload_functions(), true));

        $this->assertTrue($this->classLoader->unregister());
        $this->assertFalse(array_search([$this->classLoader, 'loadClass'], spl_autoload_functions(), true));
    }

    function testClassMap()
    {
        $className = 'Foo\Bar';
        $fileName = 'foo_bar.php';
        $className2 = 'Hello';
        $fileName2 = 'ola.php';

        $this->classLoader->addClass($className, $fileName);
        $this->classLoader->addClassMap([
            $className2 => $fileName2,
        ]);

        $this->assertSame($fileName, $this->classLoader->findFile($className));
        $this->assertSame($fileName2, $this->classLoader->findFile($className2));
        $this->assertNull($this->classLoader->findFile('Unknown\Class'));
    }

    function testPsr0Prefix()
    {
        $testDir = __DIR__ . '/fixtures/psr0';

        $this->classLoader->addPrefixes([
            'Plain\\' => $testDir,
            'Underscore_' => $testDir,
            'Combined' => $testDir,
            'Foo' => $testDir,
            'Nonexistent' => $testDir,
        ], ClassLoader::PSR0);

        $this->assertSame($testDir . '/Plain/Foo.php', $this->classLoader->findFile('Plain\Foo'));
        $this->assertSame($testDir . '/Plain/Foo.php', $this->classLoader->findFile('\Plain\Foo'));
        $this->assertSame($testDir . '/Plain/Deeper/Bar.php', $this->classLoader->findFile('Plain\Deeper\Bar'));
        $this->assertSame($testDir . '/Underscore/Foo.php', $this->classLoader->findFile('Underscore_Foo'));
        $this->assertSame($testDir . '/Underscore/Deeper/Foo.php', $this->classLoader->findFile('Underscore_Deeper_Foo'));
        $this->assertSame($testDir . '/Combined/Deeper/Foo.php', $this->classLoader->findFile('Combined\Deeper_Foo'));
        $this->assertSame($testDir . '/Combined/Underscore_In_Ns/Foo.php', $this->classLoader->findFile('Combined\Underscore_In_Ns\Foo'));
        $this->assertSame($testDir . '/Foo.php', $this->classLoader->findFile('Foo'));
        $this->assertNull($this->classLoader->findFile('Nonexistent'));
    }

    function testPsr4Prefix()
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
        $this->assertNull($this->classLoader->findFile('Nonexistent\\Foo'));
    }

    function testPsr4IncompatibleClassesDoNotMatchPsr4Prefixes()
    {
        $testDir = __DIR__ . '/fixtures/psr4';

        $this->classLoader->addPrefix('X\\', $testDir . '/incompatible');

        $this->assertNull(
            $this->classLoader->findFile('X') // X is not in a namespace
        );
    }

    function testPsr0Fallback()
    {
        $testDir = __DIR__ . '/fixtures/psr0';

        $this->classLoader->addPrefix('', $testDir, ClassLoader::PSR0);

        $this->assertSame($testDir . '/Foo.php', $this->classLoader->findFile('Foo'));
        $this->assertSame($testDir . '/Combined/Deeper/Foo.php', $this->classLoader->findFile('Combined\Deeper_Foo'));

    }

    function testPsr4Fallback()
    {
        $testDir = __DIR__ . '/fixtures/psr4';

        $this->classLoader->addPrefix('', [$testDir . '/fallback']);
        $this->classLoader->addPrefix('', [$testDir . '/fallback2']);

        $this->assertSame($testDir . '/fallback/Foo/Deeper/Bar.php', $this->classLoader->findFile('Foo\Deeper\Bar'));
        $this->assertSame($testDir . '/fallback2/Lorem/Baz.php', $this->classLoader->findFile('Lorem\Baz'));
    }

    function testExceptionOnInvalidType()
    {
        $this->expectException(\UnexpectedValueException::class);
        $this->expectExceptionMessage('Invalid prefix type');

        $this->classLoader->addPrefix('Foo', 'does_not_matter', -150);
    }

    function testExceptionOnInvalidPsr4Prefix1()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('PSR-4 prefixes must contain a top level namespace');

        $this->classLoader->addPrefix('Foo', 'does_not_matter');
    }

    function testExceptionOnInvalidPsr4Prefix2()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('PSR-4 prefixes must end with a namespace separator');

        $this->classLoader->addPrefix('Foo\Bar', 'does_not_matter');
    }

    function testSkipInvalidPaths()
    {
        $fixtureDir = __DIR__ . '/fixtures';
        $testDirPsr0 = $fixtureDir . '/psr0';
        $testDirPsr4 = $fixtureDir . '/psr4';
        $nonExistentDir = $fixtureDir . '/non-existent';

        $this->classLoader->addPrefix('Plain\\', [$nonExistentDir, $testDirPsr4 . '/plain']);
        $this->classLoader->addPrefix('Underscore_', [$nonExistentDir, $testDirPsr0], ClassLoader::PSR0);

        $this->assertSame($testDirPsr4 . '/plain/FooBar.php', $this->classLoader->findFile('Plain\FooBar'));
        $this->assertSame($testDirPsr0 . '/Underscore/Foo.php', $this->classLoader->findFile('Underscore_Foo'));
    }

    function testDebugEnabled()
    {
        $this->classLoader->setDebug(true);
        $this->assertTrue($this->classLoader->isDebugEnabled());

        $testDir = __DIR__ . '/fixtures/psr4';

        $this->classLoader->addPrefix('Plain\\', $testDir . '/plain');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('"Plain\Invalid" was not found');

        $this->classLoader->loadClass('Plain\Invalid');
    }

    function testDebugDisabled()
    {
        $this->classLoader->setDebug(false);
        $this->assertFalse($this->classLoader->isDebugEnabled());

        $testDir = __DIR__ . '/fixtures/psr4';

        $this->classLoader->addPrefix('Plain\\', $testDir . '/plain');
        $this->classLoader->loadClass('Plain\Invalid2');
    }

    function testPrefixesDisabled()
    {
        $this->classLoader->setUsePrefixes(false);

        $fixtureDir = __DIR__ . '/fixtures';
        $testDirPsr0 = $fixtureDir . '/psr0';
        $testDirPsr4 = $fixtureDir . '/psr4';

        $this->classLoader->addPrefix('Plain\\', $testDirPsr4 . '/plain');
        $this->classLoader->addPrefix('Plain\\', $testDirPsr0, ClassLoader::PSR0);
        $this->assertNull($this->classLoader->findFile('Plain\Foo'));

        $this->classLoader->addClass('Plain\Foo', 'test');
        $this->assertSame('test', $this->classLoader->findFile('Plain\Foo'));
    }

    function testPrefixesEnabled()
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

    function testDefaultFileSuffixes()
    {
        $this->assertSame($this->getDefaultFileSuffixes(), $this->classLoader->getFileSuffixes());
    }

    function testConfigureFileSuffixes()
    {
        $this->classLoader->addFileSuffix('.custom');
        $this->assertSame(array_merge($this->getDefaultFileSuffixes(), ['.custom']), $this->classLoader->getFileSuffixes());

        $this->classLoader->setFileSuffixes(['.custom']);
        $this->assertSame(['.custom'], $this->classLoader->getFileSuffixes());
    }

    function testCustomFileSuffix()
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
            ? ['.php', '.hh']
            : ['.php'];
    }
}
