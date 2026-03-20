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
namespace Presta_Shop\Module\Link_List\Controller\Admin\Improve\Design;

use Presta_Shop\Module\Link_List\Cache\Legacy_Link_Block_Cache;
use Presta_Shop\Module\Link_List\Core\Grid\Link_Block_Grid_Factory;
use Presta_Shop\Module\Link_List\Core\Search\Filters\Link_Block_Filters;
use Presta_Shop\Module\Link_List\Form\Link_Block_Form_Data_Provider;
use Presta_Shop\Module\Link_List\Repository\Link_Block_Repository;
use Presta_Shop\Presta_Shop\Core\Context\Shop_Context;
use Presta_Shop\Presta_Shop\Core\Exception\Database_Exception;
use Presta_Shop\Presta_Shop\Core\Form\Form_Handler_Interface;
use Presta_Shop_Bundle\Controller\Admin\Presta_Shop_Admin_Controller;
use Presta_Shop_Bundle\Security\Attribute\Admin_Security;
use Symfony\Component\Dependency_Injection\Attribute\Autowire;
use Symfony\Component\Http_Foundation\Redirect_Response;
use Symfony\Component\Http_Foundation\Request;
use Symfony\Component\Http_Foundation\Response;
class Link_Block_Controller extends Presta_Shop_Admin_Controller
{
    #[Admin_Security("is_granted('read', request.get('_legacy_controller'))", redirectRoute: 'admin_homepage')]
    public function list_action(Request $request, Link_Block_Repository $repository, Link_Block_Grid_Factory $link_block_grid_factory): Response
    {
        // Get hook list, then loop through hooks setting it in the filter
        $hooks = $repository->get_hooks_with_links();
        $filters_params = $this->build_filters_params_by_request($request);
        $grids = $link_block_grid_factory->get_grids($hooks, $filters_params);
        $presented_grids = [];
        foreach ($grids as $grid) {
            $presented_grids[] = $this->present_grid($grid);
        }
        $presented_grids = array_filter($presented_grids, fn(array $grid) => $grid['data']['records_total'] > 0);
        return $this->render('@Modules/ps_linklist/views/templates/admin/link_block/list.html.twig', ['grids' => $presented_grids, 'enableSidebar' => true, 'layoutHeaderToolbarBtn' => $this->get_toolbar_buttons(), 'help_link' => $this->generate_sidebar_link($request->attributes->get('_legacy_controller'))]);
    }
    #[Admin_Security("is_granted('create', request.get('_legacy_controller'))", redirectRoute: 'admin_homepage')]
    public function create_action(
        Request $request,
        Link_Block_Form_Data_Provider $link_block_form_data_provider,
        #[Autowire(service: 'prestashop.module.link_block.form_handler')]
        Form_Handler_Interface $form_handler
    ): Response
    {
        $link_block_form_data_provider->set_id_link_block(null);
        $form = $form_handler->get_form();
        return $this->render('@Modules/ps_linklist/views/templates/admin/link_block/form.html.twig', ['linkBlockForm' => $form->create_view(), 'enableSidebar' => true, 'layoutHeaderToolbarBtn' => $this->get_toolbar_buttons(), 'help_link' => $this->generate_sidebar_link($request->attributes->get('_legacy_controller'))]);
    }
    #[Admin_Security("is_granted('update', request.get('_legacy_controller'))", redirectRoute: 'admin_homepage')]
    public function edit_action(
        Request $request,
        int $link_block_id,
        Link_Block_Form_Data_Provider $link_block_form_data_provider,
        #[Autowire(service: 'prestashop.module.link_block.form_handler')]
        Form_Handler_Interface $form_handler
    ): Response
    {
        $link_block_form_data_provider->set_id_link_block($link_block_id);
        $form = $form_handler->get_form();
        return $this->render('@Modules/ps_linklist/views/templates/admin/link_block/form.html.twig', ['linkBlockForm' => $form->create_view(), 'enableSidebar' => true, 'layoutHeaderToolbarBtn' => $this->get_toolbar_buttons(), 'help_link' => $this->generate_sidebar_link($request->attributes->get('_legacy_controller'))]);
    }
    #[Admin_Security("is_granted('create', request.get('_legacy_controller'))", redirectRoute: 'admin_homepage')]
    public function create_process_action(
        Request $request,
        Link_Block_Form_Data_Provider $form_provider,
        #[Autowire(service: 'prestashop.module.link_block.form_handler')]
        Form_Handler_Interface $form_handler
    ): Redirect_Response|Response
    {
        return $this->process_form($request, 'Successful creation.', null, $form_provider, $form_handler);
    }
    #[Admin_Security("is_granted('update', request.get('_legacy_controller'))", redirectRoute: 'admin_homepage')]
    public function edit_process_action(
        Request $request,
        int $link_block_id,
        Link_Block_Form_Data_Provider $form_provider,
        #[Autowire(service: 'prestashop.module.link_block.form_handler')]
        Form_Handler_Interface $form_handler
    ): Redirect_Response|Response
    {
        return $this->process_form($request, 'Successful update.', $link_block_id, $form_provider, $form_handler);
    }
    #[Admin_Security("is_granted('delete', request.get('_legacy_controller'))", redirectRoute: 'admin_homepage')]
    public function delete_action(int $link_block_id, Legacy_Link_Block_Cache $link_block_cache, Link_Block_Repository $link_block_repository): Redirect_Response
    {
        $errors = [];
        try {
            $link_block_repository->delete($link_block_id);
        } catch (Database_Exception) {
            $errors[] = ['key' => 'Could not delete #%i', 'domain' => 'Admin.Catalog.Notification', 'parameters' => [$link_block_id]];
        }
        if (0 === count($errors)) {
            $link_block_cache->clear_module_cache();
            $this->add_flash('success', $this->trans('Successful deletion.', [], 'Admin.Notifications.Success'));
        } else {
            $this->add_flash_errors($errors);
        }
        return $this->redirect_to_route('admin_link_block_list');
    }
    #[Admin_Security("is_granted('update', request.get('_legacy_controller'))", redirectRoute: 'admin_homepage')]
    public function update_positions_action(Request $request, int $hook_id, Legacy_Link_Block_Cache $link_block_cache, Link_Block_Repository $link_block_repository, Shop_Context $shop_context): Redirect_Response
    {
        $positions_data = ['positions' => $request->request->all()['positions'], 'parentId' => $hook_id];
        try {
            $link_block_repository->update_positions($shop_context->get_id(), $positions_data);
            $link_block_cache->clear_module_cache();
            $this->add_flash('success', $this->trans('Successful update.', [], 'Admin.Notifications.Success'));
        } catch (Database_Exception $e) {
            $errors = [$e->get_message()];
            $this->add_flash_errors($errors);
        }
        return $this->redirect_to_route('admin_link_block_list');
    }
    private function process_form(
        Request $request,
        string $success_message,
        ?int $link_block_id,
        Link_Block_Form_Data_Provider $form_provider,
        #[Autowire(service: 'prestashop.module.link_block.form_handler')]
        Form_Handler_Interface $form_handler
    ): Redirect_Response|Response
    {
        $form_provider->set_id_link_block($link_block_id);
        $form = $form_handler->get_form();
        $form->handle_request($request);
        if ($form->is_submitted()) {
            if ($form->is_valid()) {
                $save_errors = $form_handler->save($form->get_data());
                if (0 === count($save_errors)) {
                    $this->add_flash('success', $this->trans($success_message, [], 'Admin.Notifications.Success'));
                    return $this->redirect_to_route('admin_link_block_list');
                }
                $this->add_flash_errors($save_errors);
            }
            $form_errors = [];
            foreach ($form->get_errors(true) as $error) {
                $form_errors[] = $error->get_message();
            }
            $this->add_flash_errors($form_errors);
        }
        return $this->render('@Modules/ps_linklist/views/templates/admin/link_block/form.html.twig', ['linkBlockForm' => $form->create_view(), 'enableSidebar' => true, 'layoutHeaderToolbarBtn' => $this->get_toolbar_buttons(), 'help_link' => $this->generate_sidebar_link($request->attributes->get('_legacy_controller'))]);
    }
    protected function build_filters_params_by_request(Request $request): array
    {
        return array_merge(Link_Block_Filters::get_defaults(), $request->query->all());
    }
    /**
     * Gets the header toolbar buttons.
     */
    private function get_toolbar_buttons(): array
    {
        return ['add' => ['href' => $this->generate_url('admin_link_block_create'), 'desc' => $this->trans('New block', [], 'Modules.Linklist.Admin'), 'icon' => 'add_circle_outline']];
    }
}