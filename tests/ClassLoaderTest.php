<?php declare(strict_types=1);

namespace Kuria\ClassLoader;

use Kuria\DevMeta\Test;

class ClassLoaderTest extends Test
{
    private const DIR_PSR0 = __DIR__ . '/Fixtures/psr-0';
    private const DIR_PSR4 = __DIR__ . '/Fixtures/psr-4';
    private const DIR_LOAD_TEST = __DIR__ . '/Fixtures/load-test';

    /** @var ClassLoader */
    private $classLoader;

    protected function setUp()
    {
        $this->classLoader = new ClassLoader();
    }

    function testShouldConfigureDebugMode()
    {
        $this->assertFalse($this->classLoader->isDebugEnabled());

        $this->classLoader->setDebug(true);

        $this->assertTrue($this->classLoader->isDebugEnabled());
    }

    function testShouldRegister()
    {
        $this->assertTrue($this->classLoader->register());
        $this->assertNotFalse(array_search([$this->classLoader, 'loadClass'], spl_autoload_functions(), true));

        $this->assertTrue($this->classLoader->unregister());
        $this->assertFalse(array_search([$this->classLoader, 'loadClass'], spl_autoload_functions(), true));
    }

    function testShouldHandleClassMap()
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

    function testShouldHandlePsr0Prefix()
    {
        $this->classLoader->addPrefixes([
            'Plain\\' => self::DIR_PSR0,
            'Underscore_' => self::DIR_PSR0,
            'Combined' => self::DIR_PSR0,
            'Foo' => self::DIR_PSR0,
            'Nonexistent' => self::DIR_PSR0,
        ], ClassLoader::PSR0);

        $this->assertSame(self::DIR_PSR0 . '/Plain/Foo.php', $this->classLoader->findFile('Plain\Foo'));
        $this->assertSame(self::DIR_PSR0 . '/Plain/Foo.php', $this->classLoader->findFile('\Plain\Foo'));
        $this->assertSame(self::DIR_PSR0 . '/Plain/Deeper/Bar.php', $this->classLoader->findFile('Plain\Deeper\Bar'));
        $this->assertSame(self::DIR_PSR0 . '/Underscore/Foo.php', $this->classLoader->findFile('Underscore_Foo'));
        $this->assertSame(self::DIR_PSR0 . '/Underscore/Deeper/Foo.php', $this->classLoader->findFile('Underscore_Deeper_Foo'));
        $this->assertSame(self::DIR_PSR0 . '/Combined/Deeper/Foo.php', $this->classLoader->findFile('Combined\Deeper_Foo'));
        $this->assertSame(self::DIR_PSR0 . '/Combined/Underscore_In_Ns/Foo.php', $this->classLoader->findFile('Combined\Underscore_In_Ns\Foo'));
        $this->assertSame(self::DIR_PSR0 . '/Foo.php', $this->classLoader->findFile('Foo'));
        $this->assertNull($this->classLoader->findFile('Nonexistent'));
    }

    function testShouldHandlePsr4Prefix()
    {
        $this->classLoader->addPrefix('Plain\\', self::DIR_PSR4 . '/plain');
        $this->classLoader->addPrefix('Under_Scored\\', self::DIR_PSR4 . '/underscored');
        $this->classLoader->addPrefix('Nonexistent\\', self::DIR_PSR4 . '/nonexistent');

        $this->assertSame(self::DIR_PSR4 . '/plain/FooBar.php', $this->classLoader->findFile('Plain\FooBar'));
        $this->assertSame(self::DIR_PSR4 . '/plain/FooBar.php', $this->classLoader->findFile('\Plain\FooBar'));
        $this->assertSame(self::DIR_PSR4 . '/plain/Deeper/Baz.php', $this->classLoader->findFile('Plain\Deeper\Baz'));
        $this->assertSame(self::DIR_PSR4 . '/underscored/Foo.php', $this->classLoader->findFile('Under_Scored\Foo'));
        $this->assertSame(self::DIR_PSR4 . '/underscored/Deeper_Foo.php', $this->classLoader->findFile('Under_Scored\Deeper_Foo'));
        $this->assertSame(self::DIR_PSR4 . '/underscored/Deeper/Foo_Bar.php', $this->classLoader->findFile('Under_Scored\Deeper\Foo_Bar'));
        $this->assertNull($this->classLoader->findFile('Nonexistent\\Foo'));
    }

    function testPsr4IncompatibleClassesShouldNotMatchPsr4Prefixes()
    {
        $this->classLoader->addPrefix('X\\', self::DIR_PSR4 . '/incompatible');

        $this->assertNull(
            $this->classLoader->findFile('X') // X is not in a namespace
        );
    }

    function testShouldHandlePsr0Fallback()
    {
        $this->classLoader->addPrefix('', self::DIR_PSR0, ClassLoader::PSR0);

        $this->assertSame(self::DIR_PSR0 . '/Foo.php', $this->classLoader->findFile('Foo'));
        $this->assertSame(self::DIR_PSR0 . '/Combined/Deeper/Foo.php', $this->classLoader->findFile('Combined\Deeper_Foo'));
    }

    function testShouldHandlePsr4Fallback()
    {
        $this->classLoader->addPrefix('', [self::DIR_PSR4 . '/fallback']);
        $this->classLoader->addPrefix('', [self::DIR_PSR4 . '/fallback2']);

        $this->assertSame(self::DIR_PSR4 . '/fallback/Foo/Deeper/Bar.php', $this->classLoader->findFile('Foo\Deeper\Bar'));
        $this->assertSame(self::DIR_PSR4 . '/fallback2/Lorem/Baz.php', $this->classLoader->findFile('Lorem\Baz'));
    }

    function testShouldThrowExceptionOnInvalidType()
    {
        $this->expectException(\UnexpectedValueException::class);
        $this->expectExceptionMessage('Invalid prefix type');

        $this->classLoader->addPrefix('Foo', 'does_not_matter', -150);
    }

    function testShouldThrowExceptionOnInvalidPsr4Prefix1()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('PSR-4 prefixes must contain a top level namespace');

        $this->classLoader->addPrefix('Foo', 'does_not_matter');
    }

    function testShouldThrowExceptionOnInvalidPsr4Prefix2()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('PSR-4 prefixes must end with a namespace separator');

        $this->classLoader->addPrefix('Foo\Bar', 'does_not_matter');
    }

    function testShouldSkipInvalidPaths()
    {
        $nonExistentDir = __DIR__ . '/Fixtures/non-existent';

        $this->classLoader->addPrefix('Plain\\', [$nonExistentDir, self::DIR_PSR4 . '/plain']);
        $this->classLoader->addPrefix('Underscore_', [$nonExistentDir, self::DIR_PSR0], ClassLoader::PSR0);

        $this->assertSame(self::DIR_PSR4 . '/plain/FooBar.php', $this->classLoader->findFile('Plain\FooBar'));
        $this->assertSame(self::DIR_PSR0 . '/Underscore/Foo.php', $this->classLoader->findFile('Underscore_Foo'));
    }

    function testShouldLoadClassInDebugMode()
    {
        $this->classLoader->setDebug(true);
        $this->classLoader->addPrefix('Kuria\\ClassLoader\\LoadTest\\', self::DIR_LOAD_TEST);
        $this->classLoader->loadClass($className = 'Kuria\ClassLoader\LoadTest\FooBar');

        $this->assertTrue(class_exists($className));
    }

    function testShouldPerformClassExistenceCheckInDebugMode()
    {
        $this->classLoader->setDebug(true);
        $this->classLoader->addPrefix('Kuria\\ClassLoader\\LoadTest\\', self::DIR_LOAD_TEST);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('"Kuria\ClassLoader\LoadTest\InvalidClassName" was not found');

        $this->classLoader->loadClass('Kuria\ClassLoader\LoadTest\InvalidClassName');
    }

    function testShouldDetectNonMatchingClassNameInDebugMode()
    {
        $this->classLoader->setDebug(true);
        $this->classLoader->addPrefix('Kuria\\ClassLoader\\LoadTest\\', self::DIR_LOAD_TEST);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage(
            'Class, interface or trait "Kuria\ClassLoader\LoadTest\Badclassnamecase"'
            . ' was loaded as "Kuria\ClassLoader\LoadTest\BadClassNameCase"'
        );

        $this->classLoader->loadClass('Kuria\ClassLoader\LoadTest\BadClassNameCase');
    }

    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    function testShouldNotPerformClassNameChecksInNonDebugMode()
    {
        $this->classLoader->addPrefix('Kuria\\ClassLoader\\LoadTest\\', self::DIR_LOAD_TEST);
        $this->classLoader->loadClass($className = 'Kuria\ClassLoader\LoadTest\BadClassNameCase');

        $this->assertTrue(class_exists($className));
    }

    function testShouldIgnorePrefixesIfPrefixesAreDisabled()
    {
        $this->classLoader->setUsePrefixes(false);

        $this->classLoader->addPrefix('Plain\\', self::DIR_PSR4 . '/plain');
        $this->classLoader->addPrefix('Plain\\', self::DIR_PSR0, ClassLoader::PSR0);
        $this->assertNull($this->classLoader->findFile('Plain\Foo'));

        $this->classLoader->addClass('Plain\Foo', 'test');
        $this->assertSame('test', $this->classLoader->findFile('Plain\Foo'));
    }

    function testShouldResolvePrefixesIfPrefixesAreEnabled()
    {
        $this->classLoader->setUsePrefixes(true);

        $this->classLoader->addPrefix('Plain\\', self::DIR_PSR4 . '/plain');
        $this->classLoader->addPrefix('Underscore_', self::DIR_PSR0, ClassLoader::PSR0);
        $this->assertSame(self::DIR_PSR4 . '/plain/FooBar.php', $this->classLoader->findFile('Plain\FooBar'));
        $this->assertSame(self::DIR_PSR0 . '/Underscore/Foo.php', $this->classLoader->findFile('Underscore_Foo'));

        $this->classLoader->addClass('Plain\Foo', 'foo');
        $this->classLoader->addClass('Underscore_Foo', 'bar');
        $this->assertSame('foo', $this->classLoader->findFile('Plain\Foo'));
        $this->assertSame('bar', $this->classLoader->findFile('Underscore_Foo'));
    }

    function testShouldHaveDefaultFileSuffixes()
    {
        $this->assertSame($this->getDefaultFileSuffixes(), $this->classLoader->getFileSuffixes());
    }

    function testShouldConfigureFileSuffixes()
    {
        $this->classLoader->addFileSuffix('.custom');
        $this->assertSame(array_merge($this->getDefaultFileSuffixes(), ['.custom']), $this->classLoader->getFileSuffixes());

        $this->classLoader->setFileSuffixes(['.custom']);
        $this->assertSame(['.custom'], $this->classLoader->getFileSuffixes());
    }

    function testShouldUseCustomFileSuffix()
    {
        $this->classLoader->addFileSuffix('.custom');

        $this->classLoader->addPrefix('Custom\\', self::DIR_PSR4 . '/custom');
        $this->classLoader->addPrefix('Custom\\', self::DIR_PSR0, ClassLoader::PSR0);

        $this->assertSame(self::DIR_PSR4 . '/custom/Foo.custom', $this->classLoader->findFile('Custom\Foo'));
        $this->assertSame(self::DIR_PSR0 . '/Custom/Bar.custom', $this->classLoader->findFile('Custom\Bar'));
    }

    private function getDefaultFileSuffixes(): array
    {
        return ['.php'];
    }
}
