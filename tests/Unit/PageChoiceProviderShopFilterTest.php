<?php

declare(strict_types=1);

namespace PrestaShop\Module\LinkList\Tests\Unit;

use PrestaShop\Module\LinkList\Form\ChoiceProvider\PageChoiceProvider;
use PrestaShop\Module\LinkList\Tests\Support\DatabaseTestCase;

final class PageChoiceProviderShopFilterTest extends DatabaseTestCase
{
    public function testMetaPageResolvedForShop(): void
    {
        $provider = new PageChoiceProvider($this->connection, _DB_PREFIX_, 1, [1], ['contact']);
        $choices = $provider->getChoices();

        self::assertSame(['Contact page' => 'contact'], $choices);
    }

    public function testMetaPageMissingForOtherShop(): void
    {
        $provider = new PageChoiceProvider($this->connection, _DB_PREFIX_, 1, [2], ['contact']);

        self::assertSame([], $provider->getChoices());
    }

    public function testMetaPageResolvedWhenShopIdsIncludeMultipleShops(): void
    {
        $provider = new PageChoiceProvider($this->connection, _DB_PREFIX_, 1, [2, 1], ['contact']);
        $choices = $provider->getChoices();

        self::assertSame(['Contact page' => 'contact'], $choices);
    }
}
