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
namespace Presta_Shop\Module\Link_List\Core\Grid\Query;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Query\Query_Builder;
use Presta_Shop\Presta_Shop\Core\Grid\Query\Abstract_Doctrine_Query_Builder;
use Presta_Shop\Presta_Shop\Core\Grid\Search\Search_Criteria_Interface;
/**
 * Class LinkBlockQueryBuilder.
 */
final class Link_Block_Query_Builder extends Abstract_Doctrine_Query_Builder
{
    /**
     * @return QueryBuilder
     */
    public function get_search_query_builder(?Search_Criteria_Interface $search_criteria = null)
    {
        $qb = $this->get_query_builder($search_criteria->get_filters());
        $qb->select('
            lb.id_link_block,
            lbl.name AS block_name,
            lb.id_hook,
            h.name as hook_name,
            h.title as hook_title,
            h.description as hook_description,
            lbs.position as position,
            GROUP_CONCAT(s.name SEPARATOR ", ") as shop_name
            ')->group_by('lb.id_link_block')->order_by($search_criteria->get_order_by(), $search_criteria->get_order_way());
        if ($search_criteria->get_limit() > 0) {
            $qb->set_first_result($search_criteria->get_offset())->set_max_results($search_criteria->get_limit());
        }
        return $qb;
    }
    /**
     * @return QueryBuilder
     */
    public function get_count_query_builder(?Search_Criteria_Interface $search_criteria = null)
    {
        $qb = $this->get_query_builder($search_criteria->get_filters());
        $qb->select('COUNT(DISTINCT(lb.id_link_block))');
        return $qb;
    }
    /**
     * Get generic query builder.
     *
     *
     * @return QueryBuilder
     */
    private function get_query_builder(array $filters)
    {
        $qb = $this->connection->create_query_builder()->from($this->db_prefix . 'link_block', 'lb')->inner_join('lb', $this->db_prefix . 'link_block_lang', 'lbl', 'lb.id_link_block = lbl.id_link_block')->left_join('lb', $this->db_prefix . 'link_block_shop', 'lbs', 'lb.id_link_block = lbs.id_link_block')->left_join('lb', $this->db_prefix . 'hook', 'h', 'lb.id_hook = h.id_hook')->left_join('lb', $this->db_prefix . 'shop', 's', 's.id_shop = lbs.id_shop');
        foreach ($filters as $name => $value) {
            if ('id_lang' === $name) {
                $qb->and_where("lbl.id_lang = :{$name}")->set_parameter($name, $value);
                continue;
            }
            if ('id_hook' === $name) {
                $qb->and_where("h.id_hook = :{$name}")->set_parameter($name, $value);
                continue;
            }
            if ('id_shop' === $name) {
                $qb->and_where("lbs.id_shop IN (:{$name})")->set_parameter($name, $value, Connection::PARAM_STR_ARRAY);
                continue;
            }
            $qb->and_where(sprintf('lbl.%s LIKE :%s', $name, $name))->set_parameter($name, '%' . $value . '%');
        }
        return $qb;
    }
}