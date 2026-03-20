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
namespace Presta_Shop\Module\Link_List\Presenter;

use Meta;
use Presta_Shop\Module\Link_List\Filter\Link_Filter;
use Presta_Shop\Module\Link_List\Model\Link_Block;
use Tools;
/**
 * Class LinkBlockPresenter.
 */
class Link_Block_Presenter
{
    private $link;
    private $language;
    /**
     * LinkBlockPresenter constructor.
     */
    public function __construct(\Link $link, \Language $language, private readonly ?Link_Filter $link_filter = new Link_Filter())
    {
        $this->link = $link;
        $this->language = $language;
    }
    /**
     *
     *
     * @throws \PrestaShopDatabaseException
     * @throws \PrestaShopException
     */
    public function present(Link_Block $cms_block): array
    {
        return ['id' => (int) $cms_block->id, 'title' => $cms_block->name[(int) $this->language->id], 'hook' => (new \Hook((int) $cms_block->id_hook))->name, 'position' => $cms_block->position, 'links' => $this->make_links($cms_block->content, $cms_block->custom_content)];
    }
    /**
     * Check the url if is an external link.
     *
     * @param string $url
     */
    public function is_external_link($url): bool
    {
        $base_link = preg_replace('#^(http)s?://#', '', (string) $this->link->get_base_link());
        $url = Tools::strtolower($url);
        if (preg_match('#^(http)s?://#', $url) && !preg_match('#^(http)s?://' . preg_quote(rtrim((string) $base_link, '/'), '/') . '#', $url)) {
            return true;
        }
        return false;
    }
    /**
     * @param array $custom_content
     *
     */
    private function make_links(array $content, $custom_content): array
    {
        $cms_links = $product_links = $statics_links = $custom_links = $category_links = [];
        if (isset($content['cms'])) {
            $cms_links = $this->make_cms_links($content['cms']);
        }
        if (isset($content['product'])) {
            $product_links = $this->make_product_links($content['product']);
        }
        if (isset($content['static'])) {
            $statics_links = $this->make_static_links($content['static']);
        }
        if (isset($content['category'])) {
            $category_links = $this->make_category_links($content['category']);
        }
        $custom_links = $this->make_custom_links($custom_content);
        return array_merge($cms_links, $product_links, $statics_links, $custom_links, $category_links);
    }
    /**
     * @param array $cmsIds
     *
     *
     * @throws \PrestaShopDatabaseException
     * @throws \PrestaShopException
     */
    private function make_cms_links($cms_ids): array
    {
        $cms_links = [];
        foreach ($cms_ids as $cms_id) {
            $cms = new \CMS((int) $cms_id);
            if (null !== $cms->id && $cms->active) {
                $cms_links[] = ['id' => 'link-cms-page-' . $cms->id, 'class' => 'cms-page-link', 'title' => $cms->meta_title[(int) $this->language->id], 'description' => $cms->meta_description[(int) $this->language->id], 'url' => $this->link->get_cms_link($cms)];
            }
        }
        return $cms_links;
    }
    /**
     * @param array $productIds
     */
    private function make_product_links($product_ids): array
    {
        $product_links = [];
        foreach ($product_ids as $product_id) {
            if (false === $product_id) {
                continue;
            }
            if ($this->is_link_disabled($product_id)) {
                continue;
            }
            $meta = \Meta::get_meta_by_page($product_id, (int) $this->language->id);
            $product_links[] = ['id' => 'link-product-page-' . $product_id, 'class' => 'cms-page-link', 'title' => $meta['title'], 'description' => $meta['description'], 'url' => $this->link->get_page_link($product_id, true)];
        }
        return $product_links;
    }
    /**
     * @param array $staticIds
     */
    private function make_static_links($static_ids): array
    {
        $static_links = [];
        foreach ($static_ids as $static_id) {
            if (false === $static_id) {
                continue;
            }
            if ($this->is_link_disabled($static_id)) {
                continue;
            }
            $meta = \Meta::get_meta_by_page($static_id, (int) $this->language->id);
            $static_links[] = ['id' => 'link-static-page-' . $static_id, 'class' => 'cms-page-link', 'title' => $meta['title'], 'description' => $meta['description'], 'url' => $this->link->get_page_link($static_id, true)];
        }
        return $static_links;
    }
    private function make_custom_links(array $custom_content): array
    {
        $custom_links = [];
        if (!isset($custom_content[$this->language->id])) {
            return $custom_links;
        }
        $custom_links = $custom_content[$this->language->id];
        $self = $this;
        return array_map(fn(array $el) => ['id' => 'link-custom-page-' . Tools::str2url($el['title']), 'class' => 'custom-page-link', 'title' => $el['title'], 'description' => '', 'url' => $el['url'], 'target' => $self->is_external_link($el['url']) ? '_blank' : ''], array_filter($custom_links));
    }
    /**
     * @param array $categoryIds
     */
    private function make_category_links($category_ids): array
    {
        $category_links = [];
        foreach ($category_ids as $category_id) {
            if (false === $category_id) {
                continue;
            }
            $meta = Meta::get_category_metas($category_id, (int) $this->language->id, '', null);
            $category_links[] = ['id' => 'link-category-' . $category_id, 'class' => 'category-link', 'title' => $meta['name'], 'description' => strip_tags((string) $meta['description']), 'url' => $this->link->get_category_link((int) $category_id)];
        }
        return $category_links;
    }
    private function is_link_disabled(string $route_id): bool
    {
        return !$this->link_filter->is_route_enabled($route_id);
    }
}