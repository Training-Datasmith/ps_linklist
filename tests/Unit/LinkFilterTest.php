<?php

declare(strict_types=1);

namespace PrestaShop\Module\LinkList\Tests\Unit;

use PHPUnit\Framework\TestCase;
use PrestaShop\Module\LinkList\Filter\BestSalesRouteFilter;
use PrestaShop\Module\LinkList\Filter\LinkFilter;

final class LinkFilterTest extends TestCase
{
    public function testUnknownRouteStaysEnabled(): void
    {
        $filter = new LinkFilter([new BestSalesRouteFilter()]);

        self::assertTrue($filter->isRouteEnabled('contact'));
    }

    public function testDisabledBestSalesRouteIsFilteredOut(): void
    {
        \Configuration::set('PS_DISPLAY_BEST_SELLERS', '0');
        $filter = new LinkFilter([new BestSalesRouteFilter()]);

        self::assertFalse($filter->isRouteEnabled('best-sales'));
    }
}
