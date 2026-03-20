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

use Configuration;
use Db;
use Hook;
use Language;
use Presta_Shop\Module\Link_List\Model\Link_Block;
/**
 * Class used to migrate data from the 1.6 module
 */
class Data_Migration
{
    public function __construct(private readonly Db $db)
    {
    }
    /**
     * Retrieve content from 1.6 module, then cleanup
     */
    public function migrate_data(): void
    {
        // Copy first table
        $this->db->execute('INSERT INTO `' . _DB_PREFIX_ . 'link_block`
            (`id_link_block`, `id_hook`, `position`)
            SELECT `id_cms_block`, `location`, `position`
            FROM `' . _DB_PREFIX_ . 'cms_block`');
        // Update hook IDs (Got from BlockCMSModel in 1.6 module)
        $relation_between_old_locations_and_hooks = [
            0 => 'displayLeftColumn',
            // LEFT_COLUMN
            1 => 'displayRightColumn',
            // RIGHT_COLUMN
            2 => 'displayFooter',
        ];
        foreach ($relation_between_old_locations_and_hooks as $old_location => $new_hook_location) {
            // Retrieve the cms page IDs linked in the old module
            $content = $this->generate_json_for_block_content(['cms' => $this->get_cms_ids_from_block($old_location)]);
            $this->db->execute('UPDATE `' . _DB_PREFIX_ . 'link_block`
                SET `id_hook` = ' . (int) Hook::get_id_by_name($new_hook_location) . ",\n                `content` = '" . p_sql($content) . "'\n                WHERE `id_hook` = " . $old_location);
        }
        // Copy second table (lang)
        $this->db->execute('INSERT INTO `' . _DB_PREFIX_ . 'link_block_lang`
            (`id_link_block`, `id_lang`, `name`)
            SELECT `id_cms_block`, `id_lang`, `name`
            FROM `' . _DB_PREFIX_ . 'cms_block_lang`');
        // Copy third table (shop)
        $this->db->execute('INSERT INTO `' . _DB_PREFIX_ . 'link_block_shop`
            (`id_link_block`, `id_shop`)
            SELECT `id_cms_block`, `id_shop`
            FROM `' . _DB_PREFIX_ . 'cms_block_shop`');
        $this->migrate_block_footer();
        // Drop old tables
        $this->db->execute('DROP TABLE `' . _DB_PREFIX_ . 'cms_block`,
            `' . _DB_PREFIX_ . 'cms_block_lang`,
            `' . _DB_PREFIX_ . 'cms_block_page`,
            `' . _DB_PREFIX_ . 'cms_block_shop`');
    }
    private function migrate_block_footer(): void
    {
        if (!Configuration::get('FOOTER_BLOCK_ACTIVATION')) {
            return;
        }
        $link_block = new Link_Block();
        $data = [];
        $footer_cms = Configuration::get('FOOTER_CMS');
        if (!empty($footer_cms)) {
            foreach (explode('|', $footer_cms) as $val) {
                list(, $cms_id) = explode('_', $val);
                $data['cms'][] = $cms_id;
            }
        }
        if (Configuration::get('FOOTER_PRICE-DROP')) {
            $data['product'][] = 'prices-drop';
        }
        if (Configuration::get('FOOTER_NEW-PRODUCTS')) {
            $data['product'][] = 'new-products';
        }
        if (Configuration::get('FOOTER_BEST-SALES')) {
            $data['product'][] = 'best-sales';
        }
        if (Configuration::get('FOOTER_CONTACT')) {
            $data['static'][] = 'contact';
        }
        if (Configuration::get('FOOTER_SITEMAP')) {
            $data['static'][] = 'sitemap';
        }
        if (Configuration::get('PS_STORES_DISPLAY_FOOTER')) {
            $data['static'][] = 'stores';
        }
        $link_block->content = $this->generate_json_for_block_content($data);
        $link_block->id_hook = (int) Hook::get_id_by_name('displayFooter');
        $languages = Language::get_languages(false);
        foreach ($languages as $lang) {
            $link_block->name[$lang['id_lang']] = 'Footer content (Migrated)';
            $link_block->custom_content[$lang['id_lang']] = json_encode([['title' => Configuration::get('FOOTER_CMS_TEXT_' . $lang['id_lang']), 'url' => '#']]);
        }
        $link_block->save();
    }
    /**
     * Generate a JSON for the column `content` of link_block
     *
     *
     * @return string
     */
    private function generate_json_for_block_content(array $data)
    {
        return json_encode(['cms' => empty($data['cms']) ? [false] : $data['cms'], 'static' => empty($data['static']) ? [false] : $data['static'], 'product' => empty($data['product']) ? [false] : $data['product']]);
    }
    /**
     * Get list of cms IDs from database for a given old cms_block_page
     *
     *
     */
    private function get_cms_ids_from_block(int $old_location): array
    {
        $request = $this->db->execute_s('SELECT id_cms FROM  `' . _DB_PREFIX_ . 'cms_block_page`
            WHERE id_cms_block = ' . $old_location . '
            AND is_category = 0');
        $ids = [];
        foreach ($request as $row) {
            $ids[] = $row['id_cms'];
        }
        return $ids;
    }
}