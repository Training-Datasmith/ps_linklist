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
namespace Presta_Shop\Module\Link_List;

use Context;
use Db;
use Hook;
use Language;
use Presta_Shop\Module\Link_List\Model\Link_Block;
use Shop;
use Symfony\Contracts\Translation\Translator_Interface as Translator;
/**
 * Class LegacyLinkBlockRepository.
 */
class Legacy_Link_Block_Repository
{
    /**
     * @var string
     */
    private $db_prefix;
    public function __construct(private readonly Db $db, private readonly Shop $shop, private readonly Translator $translator)
    {
        $this->db_prefix = $this->db->get_prefix();
    }
    /**
     * @param int $id_hook
     *
     *
     * @throws \PrestaShopDatabaseException
     * @throws \PrestaShopException
     */
    public function get_by_id_hook($id_hook): array
    {
        $id_hook = (int) $id_hook;
        $sql = "SELECT lb.`id_link_block`\n                    FROM {$this->db_prefix}link_block lb\n                    INNER JOIN {$this->db_prefix}link_block_shop lbs ON lbs.`id_link_block` = lb.`id_link_block`\n                    WHERE lb. `id_hook` = {$id_hook} AND lbs.`id_shop` = {$this->shop->id}\n                    ORDER by lbs.`position`\n                ";
        $ids = $this->db->execute_s($sql);
        $cms_block = [];
        foreach ($ids as $id) {
            $cms_block[] = new Link_Block((int) $id['id_link_block']);
        }
        return $cms_block;
    }
    public function create_tables(): bool
    {
        $engine = _MYSQL_ENGINE_;
        $success = true;
        $this->drop_tables();
        $queries = ["CREATE TABLE IF NOT EXISTS `{$this->db_prefix}link_block`(\n    \t\t\t`id_link_block` int(10) unsigned NOT NULL auto_increment,\n    \t\t\t`id_hook` int(1) unsigned DEFAULT NULL,\n    \t\t\t`position` int(10) unsigned NOT NULL default '0',\n    \t\t\t`content` text default NULL,\n    \t\t\tPRIMARY KEY (`id_link_block`)\n            ) ENGINE={$engine} DEFAULT CHARSET=utf8", "CREATE TABLE IF NOT EXISTS `{$this->db_prefix}link_block_lang`(\n    \t\t\t`id_link_block` int(10) unsigned NOT NULL,\n    \t\t\t`id_lang` int(10) unsigned NOT NULL,\n    \t\t\t`name` varchar(40) NOT NULL default '',\n    \t\t\t`custom_content` text default NULL,\n    \t\t\tPRIMARY KEY (`id_link_block`, `id_lang`)\n            ) ENGINE={$engine} DEFAULT CHARSET=utf8", "CREATE TABLE IF NOT EXISTS `{$this->db_prefix}link_block_shop` (\n    \t\t\t`id_link_block` int(10) unsigned NOT NULL auto_increment,\n    \t\t\t`id_shop` int(10) unsigned NOT NULL,\n                `position` int(10) unsigned NOT NULL default '0',\n    \t\t\tPRIMARY KEY (`id_link_block`, `id_shop`)\n            ) ENGINE={$engine} DEFAULT CHARSET=utf8"];
        foreach ($queries as $query) {
            $success &= $this->db->execute($query);
        }
        return (bool) $success;
    }
    public function drop_tables()
    {
        $sql = "DROP TABLE IF EXISTS\n\t\t\t`{$this->db_prefix}link_block`,\n\t\t\t`{$this->db_prefix}link_block_lang`,\n\t\t\t`{$this->db_prefix}link_block_shop`";
        return $this->db->execute($sql);
    }
    public function install_fixtures(): bool
    {
        $success = true;
        $id_hook = (int) Hook::get_id_by_name('displayFooter');
        $queries = ['INSERT INTO `' . $this->db_prefix . 'link_block` (`id_link_block`, `id_hook`, `position`, `content`) VALUES
                (1, ' . $id_hook . ', 0, \'{"cms":[false],"product":["prices-drop","new-products","best-sales"],"static":[false]}\'),
                (2, ' . $id_hook . ', 1, \'{"cms":["1","2","3","4","5"],"product":[false],"static":["contact","sitemap","stores"]}\');'];
        foreach (Language::get_languages(true, Context::get_context()->shop->id) as $lang) {
            $queries[] = 'INSERT INTO `' . $this->db_prefix . 'link_block_lang` (`id_link_block`, `id_lang`, `name`) VALUES
                (1, ' . (int) $lang['id_lang'] . ', "' . p_sql($this->translator->trans('Products', [], 'Modules.Linklist.Shop', $lang['locale'])) . '"),
                (2, ' . (int) $lang['id_lang'] . ', "' . p_sql($this->translator->trans('Our company', [], 'Modules.Linklist.Shop', $lang['locale'])) . '");';
        }
        foreach ($this->shop::get_context_list_shop_id() as $shop_id) {
            $queries[] = 'INSERT INTO `' . $this->db_prefix . 'link_block_shop` (`id_link_block`, `id_shop`, `position`) VALUES
                (1, ' . (int) $shop_id . ', 0),
                (2, ' . (int) $shop_id . ', 1);';
        }
        foreach ($queries as $query) {
            $success = $success && (bool) $this->db->execute($query);
        }
        return $success;
    }
}