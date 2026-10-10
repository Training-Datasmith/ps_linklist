<?php

declare(strict_types=1);

namespace PrestaShop\Module\LinkList\Tests\Unit;

use PrestaShop\Module\LinkList\Form\ChoiceProvider\CMSPageChoiceProvider;
use PrestaShop\Module\LinkList\Tests\Support\DatabaseTestCase;

final class CmsPageChoiceProviderShopFilterTest extends DatabaseTestCase
{
    public function testCmsPageVisibleOnlyForAssociatedShop(): void
    {
        $provider = new CMSPageChoiceProvider(
            $this->connection,
            _DB_PREFIX_,
            ['CMS root' => 5],
            1,
            [1]
        );

        self::assertSame([], $provider->getChoices());

        $providerShop2 = new CMSPageChoiceProvider(
            $this->connection,
            _DB_PREFIX_,
            ['CMS root' => 5],
            1,
            [2]
        );
        $choices = $providerShop2->getChoices();
        self::assertArrayHasKey('CMS root', $choices);
        self::assertContains(20, $choices['CMS root']);
    }
}
