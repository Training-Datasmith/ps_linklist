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
namespace Presta_Shop\Module\Link_List\Model;

use Data_Lang_Core;
/**
 * Class LinkBlockLang.
 */
class Link_Block_Lang extends Data_Lang_Core
{
    // Don't replace domain in init() with $this->domain for translation parsing
    protected $domain = 'Modules.Linklist.Shop';
    protected $keys = ['id_link_block'];
    protected $fields_to_update = ['name'];
    /**
     * @var array<string, array<string, string>>
     */
    public $field_names = [];
    protected function init()
    {
        $this->field_names = ['name' => [md5('Products') => $this->translator->trans('Products', [], 'Modules.Linklist.Shop', $this->locale), md5('Our company') => $this->translator->trans('Our company', [], 'Modules.Linklist.Shop', $this->locale)]];
    }
}