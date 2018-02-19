<?php declare(strict_types=1);

namespace Kuria\ClassLoader;

use PHPUnit\Framework\TestCase;

class ComposerBridgeTest extends TestCase
{
    private const TEST_DIR = __DIR__ . DIRECTORY_SEPARATOR . 'fixtures';
    
    /** @var bool set by test_autoload_file.php */
    static $testAutoloadFileFlag;
    /** @var ClassLoader */
    private $classLoader;

    protected function setUp()
    {
        static::$testAutoloadFileFlag = false;

        $this->classLoader = new ClassLoader();
    }

    function testConfigureClassLoader()
    {
        ComposerBridge::configure($this->classLoader, __DIR__ . '/fixtures');

        $this->assertTrue(static::$testAutoloadFileFlag);
        $this->assertSame(self::TEST_DIR . '/psr0/Custom/Bar.custom', $this->classLoader->findFile('Custom\Bar'));
        $this->assertSame(self::TEST_DIR . '/psr0/Combined/Deeper/Foo.php', $this->classLoader->findFile('Combined\Deeper_Foo'));
        $this->assertSame(self::TEST_DIR . '/psr0/Underscore/Foo.php', $this->classLoader->findFile('Underscore_Foo'));
        $this->assertSame(self::TEST_DIR . '/psr4/plain/FooBar.php', $this->classLoader->findFile('Plain\FooBar'));
        $this->assertSame(self::TEST_DIR . '/psr4/underscored/Foo.php', $this->classLoader->findFile('Under_Scored\Foo'));
    }

    function testConfigureClassLoaderWithoutPrefixes()
    {
        ComposerBridge::configure($this->classLoader, __DIR__ . '/fixtures', false);

        $this->assertTrue(static::$testAutoloadFileFlag);
        $this->assertSame(self::TEST_DIR . '/psr0/Custom/Bar.custom', $this->classLoader->findFile('Custom\Bar'));
        $this->assertNull($this->classLoader->findFile('Combined\Deeper_Foo'));
        $this->assertNull($this->classLoader->findFile('Plain\FooBar'));
    }
}
