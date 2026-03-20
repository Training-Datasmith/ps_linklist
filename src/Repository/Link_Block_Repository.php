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
namespace Presta_Shop\Module\Link_List\Repository;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception as DBALException;
use Doctrine\DBAL\Exception\Connection_Exception;
use Doctrine\DBAL\Query\Query_Builder;
use Doctrine\DBAL\Result;
use Hook;
use Presta_Shop\Module\Link_List\Adapter\Object_Model_Handler;
use Presta_Shop\Presta_Shop\Adapter\Shop\Context;
use Presta_Shop\Presta_Shop\Core\Exception\Database_Exception;
use Symfony\Contracts\Translation\Translator_Interface;
/**
 * Class LinkBlockRepository.
 */
class Link_Block_Repository
{
    /**
     * @var Connection
     */
    private $connection;
    /**
     * @var TranslatorInterface
     */
    private $translator;
    /**
     * @var Context
     */
    private $multi_store_context;
    /**
     * LinkBlockRepository constructor.
     *
     * @param string $dbPrefix
     */
    public function __construct(Connection $connection, private $db_prefix, private readonly array $languages, Translator_Interface $translator, private readonly bool $is_multi_store_used, Context $multi_store_context, private readonly Object_Model_Handler $object_model_handler)
    {
        $this->connection = $connection;
        $this->translator = $translator;
        $this->multi_store_context = $multi_store_context;
    }
    /**
     * Returns the list of hook with associated Link blocks.
     *
     * @return array
     */
    public function get_hooks_with_links()
    {
        $qb = $this->connection->create_query_builder();
        $qb->select('h.id_hook, h.name, h.title')->from($this->db_prefix . 'link_block', 'lb')->left_join('lb', $this->db_prefix . 'hook', 'h', 'lb.id_hook = h.id_hook')->group_by('h.id_hook')->order_by('h.name');
        return $qb->execute()->fetch_all();
    }
    /**
     *
     * @return string
     * @throws DatabaseException
     */
    public function create(array $data)
    {
        $id_hook = $data['id_hook'];
        $qb = $this->connection->create_query_builder();
        $qb->insert($this->db_prefix . 'link_block')->values(['id_hook' => ':idHook', 'content' => ':content'])->set_parameters(['idHook' => $id_hook, 'content' => json_encode(['cms' => empty($data['cms']) ? [false] : $data['cms'], 'static' => empty($data['static']) ? [false] : $data['static'], 'product' => empty($data['product']) ? [false] : $data['product'], 'category' => empty($data['category']) ? [false] : $data['category']])]);
        $this->execute_query_builder($qb, 'Link block error');
        $link_block_id = $this->connection->last_insert_id();
        $this->update_languages((int) $link_block_id, $data['block_name'], $data['custom_content']);
        $this->object_model_handler->handle_multi_shop_association((int) $link_block_id, $data['shop_association'], !$this->is_multi_store_used);
        $this->update_max_position((int) $link_block_id, $id_hook, $data['shop_association']);
        return $link_block_id;
    }
    /**
     * @param int $linkBlockId
     *
     * @throws DatabaseException
     */
    public function update($link_block_id, array $data): void
    {
        $qb = $this->connection->create_query_builder();
        $qb->update($this->db_prefix . 'link_block', 'lb')->and_where('lb.id_link_block = :linkBlockId')->set('id_hook', ':idHook')->set('content', ':content')->set_parameters(['linkBlockId' => $link_block_id, 'idHook' => $data['id_hook'], 'content' => json_encode(['cms' => empty($data['cms']) ? [false] : $data['cms'], 'static' => empty($data['static']) ? [false] : $data['static'], 'product' => empty($data['product']) ? [false] : $data['product'], 'category' => empty($data['category']) ? [false] : $data['category']])]);
        $this->execute_query_builder($qb, 'Link block error');
        $this->update_languages($link_block_id, $data['block_name'], $data['custom_content']);
        if ($this->is_multi_store_used) {
            $unassociated_shop_ids = $this->get_unassociated_shop_ids($link_block_id);
            $this->object_model_handler->handle_multi_shop_association($link_block_id, $data['shop_association']);
            // Intersects shops that were not previously associated with those just selected,
            // so that the position is updated only for the newly added shops.
            $shop_ids = array_intersect($unassociated_shop_ids, $data['shop_association']);
            if ($shop_ids) {
                $this->update_max_position((int) $link_block_id, (int) $data['id_hook'], $shop_ids);
            }
        }
    }
    /**
     * @param int $idLinkBlock
     *
     * @throws DatabaseException
     */
    public function delete($id_link_block): void
    {
        if (count($this->multi_store_context->get_all_shop_ids()) === count($this->multi_store_context->get_context_list_shop_id())) {
            $table_names = ['link_block_lang', 'link_block', 'link_block_shop'];
            foreach ($table_names as $table_name) {
                $qb = $this->connection->create_query_builder();
                $qb->delete($this->db_prefix . $table_name)->and_where('id_link_block = :idLinkBlock')->set_parameter('idLinkBlock', $id_link_block);
                $this->execute_query_builder($qb, 'Delete error');
            }
        } else if (!$this->multi_store_context->is_all_shop_context()) {
            $qb = $this->connection->create_query_builder();
            $qb->delete($this->db_prefix . 'link_block_shop')->and_where('id_link_block = :idLinkBlock')->and_where('id_shop IN (:shopIds)')->set_parameter('shopIds', $this->multi_store_context->get_context_list_shop_id(), Connection::PARAM_STR_ARRAY)->set_parameter('idLinkBlock', $id_link_block);
            $this->execute_query_builder($qb, 'Delete from multi-store tables error');
        }
    }
    public function create_tables(): array
    {
        $errors = [];
        $engine = _MYSQL_ENGINE_;
        $this->drop_tables();
        $queries = ["CREATE TABLE IF NOT EXISTS `{$this->db_prefix}link_block`(\n    \t\t\t`id_link_block` int(10) unsigned NOT NULL auto_increment,\n    \t\t\t`id_hook` int(1) unsigned DEFAULT NULL,\n                `position` int(10) unsigned NOT NULL default '0',\n    \t\t\t`content` text default NULL,\n    \t\t\tPRIMARY KEY (`id_link_block`)\n            ) ENGINE={$engine} DEFAULT CHARSET=utf8", "CREATE TABLE IF NOT EXISTS `{$this->db_prefix}link_block_lang`(\n    \t\t\t`id_link_block` int(10) unsigned NOT NULL,\n    \t\t\t`id_lang` int(10) unsigned NOT NULL,\n    \t\t\t`name` varchar(40) NOT NULL default '',\n    \t\t\t`custom_content` text default NULL,\n    \t\t\tPRIMARY KEY (`id_link_block`, `id_lang`)\n            ) ENGINE={$engine} DEFAULT CHARSET=utf8", "CREATE TABLE IF NOT EXISTS `{$this->db_prefix}link_block_shop` (\n    \t\t\t`id_link_block` int(10) unsigned NOT NULL auto_increment,\n                `id_shop` int(10) unsigned NOT NULL,\n                `position` int(10) unsigned NOT NULL default '0',\n    \t\t\tPRIMARY KEY (`id_link_block`, `id_shop`)\n            ) ENGINE={$engine} DEFAULT CHARSET=utf8"];
        foreach ($queries as $query) {
            try {
                $this->connection->execute_query($query);
            } catch (Dbal_Exception $e) {
                $errors[] = ['key' => json_encode($e->get_message()), 'parameters' => [], 'domain' => 'Admin.Modules.Notification'];
            }
        }
        return $errors;
    }
    public function install_fixtures(): array
    {
        $errors = [];
        $id_hook = (int) Hook::get_id_by_name('displayFooter');
        $queries = ['INSERT INTO `' . $this->db_prefix . 'link_block` (`id_link_block`, `id_hook`, `position`, `content`) VALUES
                (1, ' . $id_hook . ', 0, \'{"cms":[false],"product":["prices-drop","new-products","best-sales"],"static":[false],"category":[false]}\'),
                (2, ' . $id_hook . ', 1, \'{"cms":["1","2","3","4","5"],"product":[false],"static":["contact","sitemap","stores"],"category":[false]}\');'];
        foreach ($this->languages as $lang) {
            $queries[] = 'INSERT INTO `' . $this->db_prefix . 'link_block_lang` (`id_link_block`, `id_lang`, `name`) VALUES
                (1, ' . (int) $lang['id_lang'] . ', "' . p_sql($this->translator->trans('Products', [], 'Modules.Linklist.Shop', $lang['locale'])) . '"),
                (2, ' . (int) $lang['id_lang'] . ', "' . p_sql($this->translator->trans('Our company', [], 'Modules.Linklist.Shop', $lang['locale'])) . '");';
        }
        foreach ($this->multi_store_context->get_shops(true, true) as $shop_id) {
            $queries[] = 'INSERT INTO `' . $this->db_prefix . 'link_block_shop` (`id_link_block`, `id_shop`, `position`) VALUES
                (1, ' . (int) $shop_id . ', 0),
                (2, ' . (int) $shop_id . ', 1);';
        }
        foreach ($queries as $query) {
            try {
                $this->connection->execute_query($query);
            } catch (Dbal_Exception $e) {
                $errors[] = ['key' => json_encode($e->get_message()), 'parameters' => [], 'domain' => 'Admin.Modules.Notification'];
            }
        }
        return $errors;
    }
    public function drop_tables(): array
    {
        $errors = [];
        $table_names = ['link_block_shop', 'link_block_lang', 'link_block'];
        foreach ($table_names as $table_name) {
            $sql = 'DROP TABLE IF EXISTS ' . $this->db_prefix . $table_name;
            try {
                $this->connection->execute_query($sql);
            } catch (Dbal_Exception $e) {
                $errors[] = ['key' => json_encode($e->get_message()), 'parameters' => [], 'domain' => 'Admin.Modules.Notification'];
            }
        }
        return $errors;
    }
    /**
     * @param int $linkBlockId
     *
     * @throws DatabaseException
     */
    private function update_languages($link_block_id, array $block_name, array $custom): void
    {
        foreach ($this->languages as $language) {
            $qb = $this->connection->create_query_builder();
            $qb->select('lbl.id_link_block')->from($this->db_prefix . 'link_block_lang', 'lbl')->and_where('lbl.id_link_block = :linkBlockId')->and_where('lbl.id_lang = :langId')->set_parameter('linkBlockId', $link_block_id)->set_parameter('langId', $language['id_lang']);
            $found_rows = $qb->execute()->row_count();
            $qb = $this->connection->create_query_builder();
            if (!$found_rows) {
                $qb->insert($this->db_prefix . 'link_block_lang')->values(['id_link_block' => ':linkBlockId', 'id_lang' => ':langId', 'name' => ':name', 'custom_content' => ':customContent']);
            } else {
                $qb->update($this->db_prefix . 'link_block_lang', 'lbl')->set('name', ':name')->set('custom_content', ':customContent')->and_where('lbl.id_link_block = :linkBlockId')->and_where('lbl.id_lang = :langId');
            }
            $qb->set_parameters(['linkBlockId' => $link_block_id, 'langId' => $language['id_lang'], 'name' => $block_name[$language['id_lang']], 'customContent' => empty($custom) ? null : json_encode($custom[$language['id_lang']])]);
            $this->execute_query_builder($qb, 'Link block language error');
        }
    }
    /**
     *
     * @return Result|int|string
     *
     * @throws DatabaseException
     */
    private function execute_query_builder(Query_Builder $qb, string $error_prefix = 'SQL error')
    {
        try {
            $statement = $qb->execute();
        } catch (Dbal_Exception $e) {
            throw new Database_Exception($error_prefix . ': ' . var_export($e->get_message(), true));
        }
        return $statement;
    }
    private function get_hook_max_position(int $id_hook, int $id_shop): int
    {
        $qb = $this->connection->create_query_builder();
        $qb->select('COUNT(lbs.id_link_block) AS total, MAX(lbs.position) AS max_position')->from($this->db_prefix . 'link_block_shop', 'lbs')->left_join('lbs', $this->db_prefix . 'link_block', 'lb', 'lbs.id_link_block = lb.id_link_block')->and_where('lb.id_hook = :idHook')->and_where('lbs.id_shop = :idShop')->set_parameter('idHook', $id_hook)->set_parameter('idShop', $id_shop);
        $result = $qb->execute()->fetch_associative();
        $total = (int) ($result['total'] ?? 0);
        $max_position = (int) ($result['max_position'] ?? 0);
        if ($total <= 1) {
            return 0;
        }
        return $max_position + 1;
    }
    /**
     *
     * @throws DatabaseException
     */
    private function update_max_position(int $link_block_id, ?int $hook_id, array $shop_ids): void
    {
        $qb = $this->connection->create_query_builder();
        foreach ($shop_ids as $shop_id) {
            $qb->update($this->db_prefix . 'link_block_shop lbs')->set('position', ':position')->and_where('lbs.id_shop = :shopId')->and_where('lbs.id_link_block = :linkBlockId')->set_parameter('position', $this->get_hook_max_position($hook_id, $shop_id))->set_parameter('shopId', $shop_id)->set_parameter('linkBlockId', $link_block_id);
            $this->execute_query_builder($qb, 'Link block max position update error');
        }
    }
    public function update_positions(int $shop_id, array $positions_data = []): void
    {
        try {
            $this->connection->begin_transaction();
            $i = 0;
            foreach ($positions_data['positions'] as $position) {
                $qb = $this->connection->create_query_builder();
                $qb->update($this->db_prefix . 'link_block_shop')->set('position', ':position')->and_where('id_link_block = :linkBlockId')->and_where('id_shop = :shopId')->set_parameter('shopId', $shop_id)->set_parameter('linkBlockId', $position['rowId'])->set_parameter('position', $i);
                ++$i;
                try {
                    $qb->execute();
                } catch (Dbal_Exception) {
                    throw new Database_Exception('Could not update #%i');
                }
            }
            $this->connection->commit();
        } catch (Connection_Exception) {
            $this->connection->roll_back();
            throw new Database_Exception('Could not update positions.');
        }
    }
    private function get_unassociated_shop_ids(int $link_block_id): array
    {
        $qb = $this->connection->create_query_builder();
        $qb->select('s.id_shop')->from($this->db_prefix . 'shop', 's')->left_join('s', $this->db_prefix . 'link_block_shop', 'lbs', 's.id_shop = lbs.id_shop AND lbs.id_link_block = :idLinkBlock')->where('lbs.id_shop IS NULL')->set_parameter('idLinkBlock', $link_block_id);
        $rows = $qb->execute()->fetch_all_associative();
        return array_column($rows, 'id_shop');
    }
}