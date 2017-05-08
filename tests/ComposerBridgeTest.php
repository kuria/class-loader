<?php

namespace Kuria\ClassLoader;

class ComposerBridgeTest extends \PHPUnit_Framework_TestCase
{
    /** @var bool set by test_autoload_file.php */
    public static $testAutoloadFileFlag;

    /** @var ClassLoader */
    private $classLoader;

    protected function setUp()
    {
        static::$testAutoloadFileFlag = false;
        $this->classLoader = new ClassLoader();
    }

    public function testConfigureClassLoader()
    {
        ComposerBridge::configure($this->classLoader, __DIR__ . '/fixtures');

        $testDir = __DIR__ . DIRECTORY_SEPARATOR . 'fixtures';

        $this->assertTrue(static::$testAutoloadFileFlag);
        $this->assertSame($testDir . '/psr0/Custom/Bar.custom', $this->classLoader->findFile('Custom\Bar'));
        $this->assertSame($testDir . '/psr0/Combined/Deeper/Foo.php', $this->classLoader->findFile('Combined\Deeper_Foo'));
        $this->assertSame($testDir . '/psr0/Underscore/Foo.php', $this->classLoader->findFile('Underscore_Foo'));
        $this->assertSame($testDir . '/psr4/plain/FooBar.php', $this->classLoader->findFile('Plain\FooBar'));
        $this->assertSame($testDir . '/psr4/underscored/Foo.php', $this->classLoader->findFile('Under_Scored\Foo'));
    }

    public function testConfigureClassLoaderWithoutPrefixes()
    {
        ComposerBridge::configure($this->classLoader, __DIR__ . '/fixtures', false);

        $testDir = __DIR__ . DIRECTORY_SEPARATOR . 'fixtures';

        $this->assertTrue(static::$testAutoloadFileFlag);
        $this->assertSame($testDir . '/psr0/Custom/Bar.custom', $this->classLoader->findFile('Custom\Bar'));
        $this->assertFalse($this->classLoader->findFile('Combined\Deeper_Foo'));
        $this->assertFalse($this->classLoader->findFile('Plain\FooBar'));
    }
}
