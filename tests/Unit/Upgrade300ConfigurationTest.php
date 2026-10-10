<?php

declare(strict_types=1);

namespace PrestaShop\Module\LinkList\Tests\Unit;

use PHPUnit\Framework\TestCase;

final class Upgrade300ConfigurationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        \Configuration::reset();
        \Db::reset();
        foreach ([
            'FOOTER_CMS',
            'FOOTER_BLOCK_ACTIVATION',
            'FOOTER_POWEREDBY',
            'FOOTER_PRICE-DROP',
            'FOOTER_NEW-PRODUCTS',
            'FOOTER_BEST-SALES',
            'FOOTER_CONTACT',
            'FOOTER_SITEMAP',
            'PS_STORES_DISPLAY_FOOTER',
        ] as $key) {
            \Configuration::set($key, '1');
        }
    }

    public function testUpgradeDeletesEightFooterConfigurationKeys(): void
    {
        require dirname(__DIR__, 2) . '/upgrade/upgrade-3.0.0.php';
        upgrade_module_3_0_0(new \stdClass());

        $deletedKeys = [
            'FOOTER_CMS',
            'FOOTER_BLOCK_ACTIVATION',
            'FOOTER_POWEREDBY',
            'FOOTER_PRICE-DROP',
            'FOOTER_NEW-PRODUCTS',
            'FOOTER_BEST-SALES',
            'FOOTER_CONTACT',
            'FOOTER_SITEMAP',
        ];

        foreach ($deletedKeys as $key) {
            self::assertTrue(\Configuration::wasDeleted($key), $key);
        }

        self::assertFalse(\Configuration::wasDeleted('PS_STORES_DISPLAY_FOOTER'));
        self::assertNotEmpty(\Db::getInstance()->getExecuted());
    }
}
