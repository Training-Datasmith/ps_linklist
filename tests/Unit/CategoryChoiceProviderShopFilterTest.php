<?php

declare(strict_types=1);

namespace PrestaShop\Module\LinkList\Tests\Unit;

use PrestaShop\Module\LinkList\Form\ChoiceProvider\CategoryChoiceProvider;
use PrestaShop\Module\LinkList\Tests\Support\DatabaseTestCase;

final class CategoryChoiceProviderShopFilterTest extends DatabaseTestCase
{
    public function testShopFilterReturnsOnlyCategoriesForRequestedShop(): void
    {
        $provider = new CategoryChoiceProvider($this->connection, _DB_PREFIX_, 1, [2]);
        $choices = $provider->getChoices();

        self::assertCount(1, $choices);
        self::assertContains(11, $choices);
        self::assertNotContains(10, $choices);
    }

    public function testShopFilterReturnsCategoriesForMultipleRequestedShops(): void
    {
        $provider = new CategoryChoiceProvider($this->connection, _DB_PREFIX_, 1, ['1', '2']);
        $choices = $provider->getChoices();

        self::assertCount(2, $choices);
        self::assertContains(10, $choices);
        self::assertContains(11, $choices);
    }
}
