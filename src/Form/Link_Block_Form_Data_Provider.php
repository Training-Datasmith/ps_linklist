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
namespace Presta_Shop\Module\Link_List\Form;

use Hook;
use Language;
use Module;
use Presta_Shop\Module\Link_List\Cache\Link_Block_Cache_Interface;
use Presta_Shop\Module\Link_List\Model\Link_Block;
use Presta_Shop\Module\Link_List\Repository\Link_Block_Repository;
use Presta_Shop\Presta_Shop\Adapter\Configuration;
use Presta_Shop\Presta_Shop\Adapter\Shop\Context;
use Presta_Shop\Presta_Shop\Core\Form\Form_Data_Provider_Interface;
use Ps_Linklist;
/**
 * Class LinkBlockFormDataProvider.
 */
class Link_Block_Form_Data_Provider implements Form_Data_Provider_Interface
{
    private ?int $id_link_block = null;
    /**
     * @var Context
     */
    private $shop_context;
    /**
     * @var Configuration
     */
    private $configuration;
    /**
     * LinkBlockFormDataProvider constructor.
     */
    public function __construct(private readonly Link_Block_Repository $repository, private readonly Link_Block_Cache_Interface $cache, private readonly array $languages, Context $shop_context, Configuration $configuration)
    {
        $this->shop_context = $shop_context;
        $this->configuration = $configuration;
    }
    /**
     *
     * @throws \PrestaShopDatabaseException
     * @throws \PrestaShopException
     */
    public function get_data(): array
    {
        if (null === $this->id_link_block) {
            return ['link_block' => ['shop_association' => $this->shop_context->get_context_list_shop_id()]];
        }
        $link_block = new Link_Block($this->id_link_block);
        $array_link_block = $link_block->to_array();
        //The form and the database model don't have the same data hierarchy
        //Transform array $custom[en][1][name] to $custom[1][en][name]
        $array_custom = [];
        foreach ($array_link_block['custom_content'] as $id_lang => $customs) {
            if (!is_array($customs)) {
                continue;
            }
            foreach ($customs as $i => $custom) {
                $array_custom[$i][$id_lang] = $custom;
            }
        }
        return ['link_block' => ['id_link_block' => $array_link_block['id'], 'block_name' => $array_link_block['name'], 'id_hook' => $array_link_block['id_hook'], 'cms' => $array_link_block['content']['cms'] ?? [], 'product' => $array_link_block['content']['product'] ?? [], 'static' => $array_link_block['content']['static'] ?? [], 'category' => $array_link_block['content']['category'] ?? [], 'custom' => $array_custom, 'shop_association' => $array_link_block['shop_association']]];
    }
    /**
     * Make sure to fill empty multilang fields if value for default is available
     *
     *
     */
    public function prepare_data(array $link_block): array
    {
        $default_language_id = (int) $this->configuration->get('PS_LANG_DEFAULT');
        if (!empty($link_block['block_name'])) {
            foreach ($this->languages as $language) {
                if (empty($link_block['block_name'][$language['id_lang']])) {
                    $link_block['block_name'][$language['id_lang']] = $link_block['block_name'][$default_language_id];
                }
            }
        }
        if (!empty($link_block['custom'])) {
            foreach ($link_block['custom'] as $key => $custom_languages) {
                if ($this->is_empty_custom($custom_languages)) {
                    continue;
                }
                foreach ($custom_languages as $id_lang => $custom) {
                    $link_block['custom'][$key][$id_lang] = ['title' => $custom['title'] ?? $custom_languages[$default_language_id]['title'], 'url' => $custom['url'] ?? $custom_languages[$default_language_id]['url']];
                }
            }
        }
        return $link_block;
    }
    /**
     *
     * @return array
     * @throws \PrestaShop\PrestaShop\Adapter\Entity\PrestaShopDatabaseException
     */
    public function set_data(array $data)
    {
        $link_block = $this->prepare_data($data['link_block']);
        $errors = $this->validate_link_block($link_block);
        if (!empty($errors)) {
            return $errors;
        }
        $custom_content = [];
        if (!empty($link_block['custom'])) {
            foreach ($link_block['custom'] as $custom_languages) {
                if ($this->is_empty_custom($custom_languages)) {
                    continue;
                }
                foreach ($custom_languages as $id_lang => $custom) {
                    $custom_content[$id_lang][] = $custom;
                }
            }
        }
        $link_block['custom_content'] = $custom_content;
        $link_block['id_shop'] = $this->shop_context->get_context_shop_id();
        if (empty($link_block['id_link_block'])) {
            $link_block_id = $this->repository->create($link_block);
            $this->set_id_link_block((int) $link_block_id);
        } else {
            $link_block_id = $link_block['id_link_block'];
            $this->repository->update($link_block_id, $link_block);
        }
        $this->update_hook($link_block['id_hook']);
        $this->cache->clear_module_cache();
        return [];
    }
    public function get_id_link_block(): ?int
    {
        return $this->id_link_block;
    }
    public function set_id_link_block(?int $id_link_block): static
    {
        $this->id_link_block = $id_link_block;
        return $this;
    }
    private function validate_link_block(array $data): array
    {
        $errors = [];
        if (!isset($data['id_hook'])) {
            $errors[] = ['key' => 'Missing id_hook', 'domain' => 'Admin.Catalog.Notification', 'parameters' => []];
        }
        if (!isset($data['block_name'])) {
            $errors[] = ['key' => 'Missing block_name', 'domain' => 'Admin.Catalog.Notification', 'parameters' => []];
        } else {
            foreach ($this->languages as $language) {
                if (empty($data['block_name'][$language['id_lang']])) {
                    $errors[] = ['key' => 'Missing block_name value for language %s', 'domain' => 'Admin.Catalog.Notification', 'parameters' => [$language['iso_code']]];
                }
            }
        }
        if (!isset($data['custom'])) {
            return $errors;
        }
        foreach ($data['custom'] as $custom_index => $custom) {
            if ($this->is_empty_custom($custom)) {
                continue;
            }
            $default_language_id = (int) $this->configuration->get('PS_LANG_DEFAULT');
            $fields = ['title', 'url'];
            foreach ($fields as $field) {
                if (empty($custom[$default_language_id][$field])) {
                    $errors[] = ['key' => 'Missing %s value in custom[%s] for language %s', 'domain' => 'Admin.Catalog.Notification', 'parameters' => [$field, $custom_index, Language::get_iso_by_id($default_language_id)]];
                }
            }
        }
        return $errors;
    }
    private function is_empty_custom(array $custom): bool
    {
        $fields = ['title', 'url'];
        foreach ($custom as $lang_custom) {
            foreach ($fields as $field) {
                if (!empty($lang_custom[$field])) {
                    return false;
                }
            }
        }
        return true;
    }
    /**
     * Register the selected hook to this module if it was not registered yet.
     *
     * @param int $hookId
     *
     * @throws \PrestaShopException
     */
    private function update_hook($hook_id): void
    {
        $hook_name = Hook::get_name_by_id($hook_id);
        $module = Module::get_instance_by_name(Ps_Linklist::MODULE_NAME);
        if ($module instanceof Module && !Hook::is_module_registered_on_hook($module, $hook_name, $this->shop_context->get_context_shop_id())) {
            Hook::register_hook($module, $hook_name);
        }
    }
}