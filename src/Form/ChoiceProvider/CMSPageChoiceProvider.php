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

use Doctrine\DBAL\Connection;
/**
 * Class CMSPageChoiceProvider.
 */
final class Cms_Page_Choice_Provider extends Abstract_Database_Choice_Provider
{
    /**
     * CMSPageChoiceProvider constructor.
     *
     * @param string $dbPrefix
     * @param int $idLang
     * @param array $shopIds
     */
    public function __construct(Connection $connection, $db_prefix, private readonly array $categories, $id_lang, ?array $shop_ids)
    {
        parent::__construct($connection, $db_prefix, $id_lang, $shop_ids);
    }
    public function get_choices(): array
    {
        $choices = [];
        foreach ($this->categories as $category_name => $category_id) {
            $qb = $this->connection->create_query_builder();
            $qb->select('c.id_cms, cl.meta_title')->from($this->db_prefix . 'cms', 'c')->inner_join('c', $this->db_prefix . 'cms_lang', 'cl', 'c.id_cms = cl.id_cms')->inner_join('c', $this->db_prefix . 'cms_shop', 'cs', 'c.id_cms = cs.id_cms')->and_where('c.active = 1')->and_where('cl.id_lang = :idLang')->and_where('cs.id_shop IN (:shopIds)')->and_where('c.id_cms_category = :idCmsCategory')->set_parameter('idCmsCategory', $category_id)->set_parameter('idLang', $this->id_lang)->set_parameter('shopIds', implode(',', $this->shop_ids))->order_by('c.position');
            $pages = $qb->execute()->fetch_all();
            foreach ($pages as $page) {
                $choices[$category_name][$page['id_cms'] . ' ' . $page['meta_title']] = $page['id_cms'];
            }
        }
        return $choices;
    }
}