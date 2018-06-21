<?php declare(strict_types=1);

namespace Kuria\ClassLoader;

use PHPUnit\Framework\TestCase;

class ComposerBridgeTest extends TestCase
{
    private const TEST_DIR = __DIR__ . DIRECTORY_SEPARATOR . 'Fixtures';

    /** @var bool set by test_autoload_file.php */
    static $testAutoloadFileFlag;
    /** @var ClassLoader */
    private $classLoader;

    protected function setUp()
    {
        static::$testAutoloadFileFlag = false;

        $this->classLoader = new ClassLoader();
    }

    function testShouldConfigureClassLoader()
    {
        ComposerBridge::configure($this->classLoader, self::TEST_DIR);

        $this->assertTrue(static::$testAutoloadFileFlag);
        $this->assertSame(self::TEST_DIR . '/psr-0/Custom/Bar.custom', $this->classLoader->findFile('Custom\Bar'));
        $this->assertSame(self::TEST_DIR . '/psr-0/Combined/Deeper/Foo.php', $this->classLoader->findFile('Combined\Deeper_Foo'));
        $this->assertSame(self::TEST_DIR . '/psr-0/Underscore/Foo.php', $this->classLoader->findFile('Underscore_Foo'));
        $this->assertSame(self::TEST_DIR . '/psr-4/plain/FooBar.php', $this->classLoader->findFile('Plain\FooBar'));
        $this->assertSame(self::TEST_DIR . '/psr-4/underscored/Foo.php', $this->classLoader->findFile('Under_Scored\Foo'));
    }

    function testShouldConfigureClassLoaderWithoutPrefixes()
    {
        ComposerBridge::configure($this->classLoader, self::TEST_DIR, false);

        $this->assertTrue(static::$testAutoloadFileFlag);
        $this->assertSame(self::TEST_DIR . '/psr-0/Custom/Bar.custom', $this->classLoader->findFile('Custom\Bar'));
        $this->assertNull($this->classLoader->findFile('Combined\Deeper_Foo'));
        $this->assertNull($this->classLoader->findFile('Plain\FooBar'));
    }
}
