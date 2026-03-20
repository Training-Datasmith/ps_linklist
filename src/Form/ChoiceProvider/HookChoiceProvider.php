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
namespace Presta_Shop\Module\Link_List\Form\Choice_Provider;

/**
 * Class HookChoiceProvider.
 */
final class Hook_Choice_Provider extends Abstract_Database_Choice_Provider
{
    /**
     * @return mixed[]
     */
    public function get_choices(): array
    {
        $qb = $this->connection->create_query_builder();
        $qb->select('h.id_hook, h.name')->from($this->db_prefix . 'hook', 'h')->and_where('h.name LIKE :displayHook')->set_parameter('displayHook', 'display%')->order_by('h.name');
        $hooks = $qb->execute()->fetch_all();
        $choices = [];
        foreach ($hooks as $hook) {
            $choices[$hook['name']] = $hook['id_hook'];
        }
        return $choices;
    }
}