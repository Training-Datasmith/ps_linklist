<?php

declare(strict_types=1);

namespace PrestaShop\Module\LinkList\Tests\Unit;

use PHPUnit\Framework\TestCase;

final class TestsIndexPhpTest extends TestCase
{
    public function testTestsIndexPhpRunsWithoutNotices(): void
    {
        $indexPath = dirname(__DIR__) . '/index.php';
        $phpBinary = getenv('TEST_PHP_BINARY') ?: 'php';
        $command = sprintf(
            '%s -d error_reporting=-1 -d display_errors=1 %s 2>&1',
            escapeshellarg($phpBinary),
            escapeshellarg($indexPath)
        );
        exec($command, $output, $exitCode);

        self::assertSame(0, $exitCode);
        self::assertSame('', implode("\n", $output));
    }
}
