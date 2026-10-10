<?php

declare(strict_types=1);

namespace PrestaShop\Module\LinkList\Tests\Unit;

use PHPUnit\Framework\TestCase;
use PrestaShop\Module\LinkList\Filter\BestSalesRouteFilter;

final class BestSalesRouteFilterTest extends TestCase
{
    public function testSupportsOnlyBestSalesRoute(): void
    {
        $filter = new BestSalesRouteFilter();

        self::assertTrue($filter->supports('best-sales'));
        self::assertFalse($filter->supports('new-products'));
    }

    public function testRouteEnabledFollowsConfigurationFlag(): void
    {
        \Configuration::set('PS_DISPLAY_BEST_SELLERS', '1');
        $filter = new BestSalesRouteFilter();

        self::assertTrue($filter->isRouteEnabled('best-sales'));
    }
}
