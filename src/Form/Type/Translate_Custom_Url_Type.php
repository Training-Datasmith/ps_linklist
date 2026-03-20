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

use Presta_Shop_Bundle\Form\Admin\Type\Translatable_Type;
use Symfony\Component\Form\Form_Builder_Interface;
/**
 * Class TranslatableUrlType.
 */
class Translate_Custom_Url_Type extends Translatable_Type
{
    /**
     * {@inheritdoc}
     */
    public function build_form(Form_Builder_Interface $builder, array $options): void
    {
        foreach ($options['locales'] as $locale) {
            $locale_options = $options['options'];
            $locale_options['label'] = $locale['iso_code'];
            if (!isset($locale_options['required'])) {
                $locale_options['required'] = false;
            }
            $builder->add($locale['id_lang'], Custom_Url_Type::class, $locale_options);
        }
    }
}