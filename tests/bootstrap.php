<?php declare(strict_types=1);

namespace {
    require __DIR__ . '/../vendor/autoload.php';
}

namespace Kuria\ClassLoader {
    /**
     * @internal
     */
    function test_fail_file_included(string $file): void
    {
        throw new \PHPUnit\Framework\AssertionFailedError(sprintf('File "%s" should not have been included', $file));
    }
}
