<?php

declare (strict_types=1);
/**
 * Copyright since 2007 PrestaShop SA and Contributors
 * PrestaShop is an International Registered Trademark & Property of PrestaShop SA
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Academic Free License version 3.0
 * that is bundled with this package in the file LICENSE.md.
 * It is also available through the world-wide-web at this URL:
 * https://opensource.org/licenses/AFL-3.0
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@prestashop.com so we can send you a copy immediately.
 *
 * @author    PrestaShop SA and Contributors <contact@prestashop.com>
 * @copyright Since 2007 PrestaShop SA and Contributors
 * @license   https://opensource.org/licenses/AFL-3.0 Academic Free License version 3.0
 */
namespace Presta_Shop\Module\Link_List\Filter;

class Link_Filter
{
    /**
     * @var RouteFilterInterface[]
     */
    private array $route_filters = [];
    public function __construct(array $route_filters = [])
    {
        $this->add_route_filter(...$route_filters);
    }
    public function add_route_filter(Route_Filter_Interface ...$route_filters): void
    {
        foreach ($route_filters as $route_filter) {
            $this->route_filters[] = $route_filter;
        }
    }
    public function is_route_enabled(string $route_id): bool
    {
        foreach ($this->route_filters as $filter) {
            if ($filter->supports($route_id) && !$filter->is_route_enabled($route_id)) {
                return false;
            }
        }
        return true;
    }
}