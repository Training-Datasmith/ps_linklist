<?php

declare(strict_types=1);

namespace PrestaShop\Module\LinkList\Tests\Unit;

use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;

final class SuiteManifestTest extends TestCase
{
    private const EXPECTED_TEST_COUNT = 29;

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
        self::assertSame(self::EXPECTED_TEST_COUNT, self::countExecutableTests());
    }

    private static function countExecutableTests(): int
    {
        $total = 0;
        foreach (self::EXPECTED_FILES as $file) {
            $class = __NAMESPACE__ . '\\' . substr($file, 0, -4);
            $ref = new ReflectionClass($class);
            foreach ($ref->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
                if (!str_starts_with($method->getName(), 'test')) {
                    continue;
                }
                $doc = $method->getDocComment() ?: '';
                if (preg_match('/@dataProvider\s+(\S+)/', $doc, $matches)) {
                    $provider = $matches[1];
                    if (str_contains($provider, '::')) {
                        $rows = call_user_func($provider);
                    } else {
                        $rows = $class::$provider();
                    }
                    $total += count($rows);

                    continue;
                }
                ++$total;
            }
        }

        return $total;
    }
}
