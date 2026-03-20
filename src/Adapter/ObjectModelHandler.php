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
namespace Presta_Shop\Module\Link_List\Adapter;

use Presta_Shop\Module\Link_List\Model\Link_Block;
use Presta_Shop\Presta_Shop\Adapter\Domain\Abstract_Object_Model_Handler;
class Object_Model_Handler extends Abstract_Object_Model_Handler
{
    public function handle_multi_shop_association(int $link_block_id, array $associated_shops, bool $force_associate = false): void
    {
        $object_model = new Link_Block($link_block_id);
        /*
         * Why we want to force association?
         * It's easier to work on multi-store tables even when feature is disabled
         * This way we can force association to store as legacy ObjectModel does
         * We need to remember that multi-store is always there, shop tables are always there
         *
         * @todo: this should be part of AbstractObjectModelHandler
         */
        if ($force_associate) {
            $object_model->associate_to($associated_shops);
            return;
        }
        $this->associate_with_shops($object_model, $associated_shops);
    }
}