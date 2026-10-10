<?php

declare(strict_types=1);

namespace PrestaShop\Module\LinkList\Tests\Unit;

use PrestaShop\Module\LinkList\Form\ChoiceProvider\HookChoiceProvider;
use PrestaShop\Module\LinkList\Tests\Support\DatabaseTestCase;

final class HookChoiceProviderTest extends DatabaseTestCase
{
    public function testListsDisplayHooks(): void
    {
        $provider = new HookChoiceProvider($this->connection, _DB_PREFIX_, 1, [1]);
        $choices = $provider->getChoices();

        self::assertSame(['displayFooter' => 1], $choices);
    }
}
