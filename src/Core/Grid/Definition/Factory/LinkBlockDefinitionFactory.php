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
namespace Presta_Shop\Module\Link_List\Core\Grid\Definition\Factory;

use Presta_Shop\Presta_Shop\Core\Grid\Action\Row\Row_Action_Collection;
use Presta_Shop\Presta_Shop\Core\Grid\Action\Row\Type\Link_Row_Action;
use Presta_Shop\Presta_Shop\Core\Grid\Action\Row\Type\Submit_Row_Action;
use Presta_Shop\Presta_Shop\Core\Grid\Column\Column_Collection;
use Presta_Shop\Presta_Shop\Core\Grid\Column\Type\Common\Action_Column;
use Presta_Shop\Presta_Shop\Core\Grid\Column\Type\Common\Position_Column;
use Presta_Shop\Presta_Shop\Core\Grid\Column\Type\Data_Column;
use Presta_Shop\Presta_Shop\Core\Grid\Definition\Factory\Abstract_Grid_Definition_Factory;
use Presta_Shop\Presta_Shop\Core\Hook\Hook_Dispatcher_Interface;
use Presta_Shop\Presta_Shop\Core\Multistore\Multistore_Context_Checker_Interface;
/**
 * Class LinkBlockDefinitionFactory.
 */
final class Link_Block_Definition_Factory extends Abstract_Grid_Definition_Factory
{
    public const FACTORY_ID = 'link_widget_grid_';
    /**
     * @var MultistoreContextCheckerInterface
     */
    private $multistore_context_checker;
    /**
     * LinkBlockDefinitionFactory constructor.
     */
    public function __construct(private array $hook, Multistore_Context_Checker_Interface $multistore_context_checker, Hook_Dispatcher_Interface $hook_dispatcher)
    {
        parent::__construct($hook_dispatcher);
        $this->multistore_context_checker = $multistore_context_checker;
    }
    /**
     * {@inheritdoc}
     */
    protected function get_id()
    {
        return self::FACTORY_ID . $this->hook['id_hook'];
    }
    /**
     * {@inheritdoc}
     */
    protected function get_name()
    {
        return $this->hook['name'] . ' ' . $this->hook['title'];
    }
    /**
     * {@inheritdoc}
     */
    protected function get_columns()
    {
        $columns = (new Column_Collection())->add((new Data_Column('id_link_block'))->set_name($this->trans('ID', [], 'Modules.Linklist.Admin'))->set_options(['field' => 'id_link_block']))->add((new Data_Column('block_name'))->set_name($this->trans('Name of the block', [], 'Modules.Linklist.Admin'))->set_options(['field' => 'block_name']))->add((new Action_Column('actions'))->set_options(['actions' => (new Row_Action_Collection())->add((new Link_Row_Action('edit'))->set_icon('edit')->set_options(['route' => 'admin_link_block_edit', 'route_param_name' => 'linkBlockId', 'route_param_field' => 'id_link_block']))->add((new Submit_Row_Action('delete'))->set_name($this->trans('Delete', [], 'Admin.Actions'))->set_icon('delete')->set_options(['method' => 'POST', 'route' => 'admin_link_block_delete', 'route_param_name' => 'linkBlockId', 'route_param_field' => 'id_link_block', 'confirm_message' => $this->trans('Delete selected item?', [], 'Admin.Notifications.Warning')]))]));
        if ($this->multistore_context_checker->is_single_shop_context()) {
            $columns->add_before('actions', (new Position_Column('position'))->set_name($this->trans('Position', [], 'Admin.Global'))->set_options(['id_field' => 'id_link_block', 'position_field' => 'position', 'update_route' => 'admin_link_block_update_positions', 'update_method' => 'POST', 'record_route_params' => ['id_hook' => 'hookId']]));
        } else {
            $columns->add_before('actions', (new Data_Column('shop_name'))->set_name($this->trans('Shop', [], 'Admin.Global'))->set_options(['field' => 'shop_name', 'sortable' => false]));
        }
        return $columns;
    }
}