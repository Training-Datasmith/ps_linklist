<?php

declare(strict_types=1);

namespace PrestaShop\Module\LinkList\Tests\Unit;

use PHPUnit\Framework\TestCase;

final class SuiteManifestTest extends TestCase
{
    private const EXPECTED_TEST_COUNT = 26;

    /** @var list<string> */
    private const EXPECTED_FILES = [
        'AdminRoutesYamlTest.php',
        'BestSalesRouteFilterTest.php',
        'CategoryChoiceProviderShopFilterTest.php',
        'CmsCategoryChoiceProviderTest.php',
        'CmsPageChoiceProviderShopFilterTest.php',
        'HookChoiceProviderTest.php',
        'LinkBlockPresenterExternalLinkTest.php',
        'LinkBlockQueryBuilderTest.php',
        'LinkBlockRepositorySchemaTest.php',
        'LinkBlockRepositoryUpdatePositionsTest.php',
        'LinkFilterTest.php',
        'PageChoiceProviderShopFilterTest.php',
        'SuiteManifestTest.php',
        'TestsIndexPhpTest.php',
        'Upgrade300ConfigurationTest.php',
    ];

    public function testManifestFileListIsComplete(): void
    {
        $unitDir = dirname(__DIR__) . '/Unit';
        $actual = array_values(array_filter(scandir($unitDir) ?: [], static fn (string $f): bool => str_ends_with($f, 'Test.php')));
        sort($actual);

        self::assertSame(self::EXPECTED_FILES, $actual);
    }

    public function testManifestTestCountMatchesPhpUnitInventory(): void
    {
        self::assertSame(self::EXPECTED_TEST_COUNT, (int) (getenv('TEST_SUITE_METHOD_COUNT') ?: 0));
    }
}
