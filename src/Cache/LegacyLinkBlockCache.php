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
namespace Presta_Shop\Module\Link_List\Cache;

use Module;
use Ps_Linklist;
/**
 * Class LegacyBlockCache.
 */
final class Legacy_Link_Block_Cache implements Link_Block_Cache_Interface
{
    /**
     * {@inheritdoc}
     */
    public function clear_module_cache(): void
    {
        /** @var Ps_Linklist $module */
        $module = Module::get_instance_by_name(Ps_Linklist::MODULE_NAME);
        $module->_clear_cache($module->template_file);
    }
}