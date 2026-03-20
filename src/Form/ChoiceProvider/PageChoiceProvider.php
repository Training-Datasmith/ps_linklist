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
use Presta_Shop\Presta_Shop\Core\Foundation\Database\Entity_Not_Found_Exception;
use Tools;
/**
 * Class PageChoiceProvider.
 */
final class Page_Choice_Provider extends Abstract_Database_Choice_Provider
{
    /**
     * PageChoiceProvider constructor.
     *
     * @param string $dbPrefix
     * @param int $idLang
     */
    public function __construct(Connection $connection, $db_prefix, $id_lang, array $shop_ids, private readonly array $page_names)
    {
        parent::__construct($connection, $db_prefix, $id_lang, $shop_ids);
    }
    /**
     * @throws EntityNotFoundException
     */
    public function get_choices(): array
    {
        $choices = [];
        foreach ($this->page_names as $page_name) {
            $qb = $this->connection->create_query_builder();
            $qb->select('m.id_meta, ml.title')->from($this->db_prefix . 'meta', 'm')->left_join('m', $this->db_prefix . 'meta_lang', 'ml', 'm.id_meta = ml.id_meta')->and_where($qb->expr()->or_x('m.page = :page', 'm.page = :pageSlug'))->and_where('ml.id_lang = :idLang')->and_where('ml.id_shop IN (:shopIds)')->set_parameter('idLang', $this->id_lang)->set_parameter('shopIds', implode(',', $this->shop_ids))->set_parameter('page', $page_name)->set_parameter('pageSlug', str_replace('-', '', Tools::strtolower($page_name)));
            $meta = $qb->execute()->fetch_all();
            if (!empty($meta)) {
                $choices[$meta[0]['title']] = $page_name;
            }
        }
        return $choices;
    }
}