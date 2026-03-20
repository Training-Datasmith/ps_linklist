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
namespace Presta_Shop\Module\Link_List\Core\Grid;

use Presta_Shop\Module\Link_List\Core\Grid\Definition\Factory\Link_Block_Definition_Factory;
use Presta_Shop\Module\Link_List\Core\Search\Filters\Link_Block_Filters;
use Presta_Shop\Presta_Shop\Adapter\Shop\Context;
use Presta_Shop\Presta_Shop\Core\Grid\Data\Factory\Grid_Data_Factory_Interface;
use Presta_Shop\Presta_Shop\Core\Grid\Filter\Grid_Filter_Form_Factory_Interface;
use Presta_Shop\Presta_Shop\Core\Grid\Grid_Factory;
use Presta_Shop\Presta_Shop\Core\Grid\Grid_Interface;
use Presta_Shop\Presta_Shop\Core\Hook\Hook_Dispatcher_Interface;
use Symfony\Contracts\Translation\Translator_Interface;
/**
 * Class LinkBlockGridFactory.
 */
final class Link_Block_Grid_Factory
{
    /**
     * @var TranslatorInterface
     */
    private $translator;
    /**
     * @var HookDispatcherInterface
     */
    private $hook_dispatcher;
    /**
     * @var GridDataFactoryInterface
     */
    private $data_factory;
    /**
     * @var GridFilterFormFactoryInterface
     */
    private $filter_form_factory;
    /**
     * @var Context
     */
    private $shop_context;
    /**
     * HookGridFactory constructor.
     */
    public function __construct(Translator_Interface $translator, Grid_Data_Factory_Interface $data_factory, Hook_Dispatcher_Interface $hook_dispatcher, Grid_Filter_Form_Factory_Interface $filter_form_factory, Context $shop_context)
    {
        $this->translator = $translator;
        $this->hook_dispatcher = $hook_dispatcher;
        $this->data_factory = $data_factory;
        $this->filter_form_factory = $filter_form_factory;
        $this->shop_context = $shop_context;
    }
    /**
     *
     * @return GridInterface[]
     */
    public function get_grids(array $hooks, array $filters_params): array
    {
        $grids = [];
        foreach ($hooks as $hook) {
            $hook_params = $filters_params;
            $hook_params['filters']['id_hook'] = $hook['id_hook'];
            $hook_params['filters']['id_shop'] = $this->shop_context->get_context_list_shop_id();
            $filters = new Link_Block_Filters($hook_params);
            $grid_factory = $this->build_grid_factory_by_hook($hook);
            $grids[] = $grid_factory->get_grid($filters);
        }
        return $grids;
    }
    /**
     * Each definition depends on the hook, therefore each factory also
     * depends on the hook.
     *
     *
     * @return GridFactory
     */
    private function build_grid_factory_by_hook(array $hook)
    {
        $definition_factory = new Link_Block_Definition_Factory($hook, $this->shop_context, $this->hook_dispatcher);
        $definition_factory->set_translator($this->translator);
        return new Grid_Factory($definition_factory, $this->data_factory, $this->filter_form_factory, $this->hook_dispatcher);
    }
}