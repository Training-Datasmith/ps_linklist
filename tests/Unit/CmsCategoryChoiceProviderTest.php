<?php

declare(strict_types=1);

namespace PrestaShop\Module\LinkList\Tests\Unit;

use PrestaShop\Module\LinkList\Form\ChoiceProvider\CMSCategoryChoiceProvider;
use PrestaShop\Module\LinkList\Tests\Support\DatabaseTestCase;

final class CmsCategoryChoiceProviderTest extends DatabaseTestCase
{
    public function testReturnsActiveCategoriesForShop(): void
    {
        $provider = new CMSCategoryChoiceProvider($this->connection, _DB_PREFIX_, 1, [1]);
        $choices = $provider->getChoices();

        self::assertSame(['CMS root' => 5], $choices);
    }

    public function testReturnsCategoriesForMultipleRequestedShops(): void
    {
        $provider = new CMSCategoryChoiceProvider($this->connection, _DB_PREFIX_, 1, [1, 2]);
        $choices = $provider->getChoices();

        self::assertCount(2, $choices);
        self::assertSame(5, $choices['CMS root']);
        self::assertSame(6, $choices['CMS shop2 only']);
    }
}
