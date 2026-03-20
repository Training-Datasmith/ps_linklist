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
namespace Presta_Shop\Module\Link_List\Form\Type;

use Presta_Shop\Presta_Shop\Core\Constraint_Validator\Constraints\Default_Language;
use Presta_Shop_Bundle\Form\Admin\Type\Shop_Choice_Tree_Type;
use Presta_Shop_Bundle\Form\Admin\Type\Translatable_Type;
use Presta_Shop_Bundle\Form\Admin\Type\Translator_Aware_Type;
use Symfony\Component\Form\Extension\Core\Type\Choice_Type;
use Symfony\Component\Form\Extension\Core\Type\Collection_Type;
use Symfony\Component\Form\Extension\Core\Type\Hidden_Type;
use Symfony\Component\Form\Form_Builder_Interface;
use Symfony\Component\Options_Resolver\Options_Resolver;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\Not_Blank;
use Symfony\Contracts\Translation\Translator_Interface;
class Link_Block_Type extends Translator_Aware_Type
{
    /**
     * @var TranslatorInterface
     */
    private $translator;
    /**
     * LinkBlockType constructor.
     */
    public function __construct(Translator_Interface $translator, array $locales, private readonly array $hook_choices, private readonly array $cms_page_choices, private readonly array $product_page_choices, private readonly array $static_page_choices, private readonly array $category_choices, private readonly bool $is_multi_store_used)
    {
        parent::__construct($translator, $locales);
        $this->translator = $translator;
    }
    /**
     * {@inheritdoc}
     */
    public function build_form(Form_Builder_Interface $builder, array $options): void
    {
        $builder->add('id_link_block', Hidden_Type::class)->add('block_name', Translatable_Type::class, ['locales' => $this->locales, 'required' => true, 'label' => $this->trans('Name of the block', 'Modules.Linklist.Admin'), 'constraints' => [new Default_Language()], 'options' => ['constraints' => [new Length(['max' => 40, 'maxMessage' => $this->translator->trans('Name of the block cannot be longer than %limit% characters', ['%limit%' => 40], 'Modules.Linklist.Admin')])]]])->add('id_hook', Choice_Type::class, ['choices' => $this->hook_choices, 'attr' => ['data-toggle' => 'select2', 'data-minimumResultsForSearch' => '7'], 'label' => $this->trans('Hook', 'Admin.Global')])->add('cms', Choice_Type::class, ['choices' => $this->cms_page_choices, 'label' => $this->trans('Content pages', 'Modules.Linklist.Admin'), 'multiple' => true, 'expanded' => true])->add('product', Choice_Type::class, ['choices' => $this->product_page_choices, 'label' => $this->trans('Product pages', 'Modules.Linklist.Admin'), 'multiple' => true, 'expanded' => true])->add('category', Choice_Type::class, ['choices' => $this->category_choices, 'label' => $this->trans('Categories', 'Modules.Linklist.Admin'), 'multiple' => true, 'expanded' => true])->add('static', Choice_Type::class, ['choices' => $this->static_page_choices, 'label' => $this->trans('Static content', 'Modules.Linklist.Admin'), 'multiple' => true, 'expanded' => true])->add('custom', Collection_Type::class, ['entry_type' => Translate_Custom_Url_Type::class, 'entry_options' => ['locales' => $this->locales, 'label' => false], 'attr' => ['class' => 'custom_collection', 'data-delete-button-label' => $this->trans('Delete', 'Admin.Global')], 'allow_add' => true, 'allow_delete' => true, 'label' => $this->trans('Custom content', 'Modules.Linklist.Admin')]);
        if ($this->is_multi_store_used) {
            $builder->add('shop_association', Shop_Choice_Tree_Type::class, ['label' => $this->trans('Shop association', 'Admin.Global'), 'required' => false, 'constraints' => [new Not_Blank(['message' => $this->trans('You have to select at least one shop to associate this item with', 'Admin.Notifications.Error')])]]);
        }
    }
    /**
     * {@inheritdoc}
     */
    public function configure_options(Options_Resolver $resolver): void
    {
        $resolver->set_defaults(['label' => false]);
    }
    /**
     * {@inheritdoc}
     */
    public function get_block_prefix()
    {
        return 'module_link_block';
    }
}