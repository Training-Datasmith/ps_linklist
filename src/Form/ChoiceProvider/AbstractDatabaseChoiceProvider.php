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
namespace Presta_Shop\Module\Link_List\Form\Choice_Provider;

use Doctrine\DBAL\Connection;
use Presta_Shop\Presta_Shop\Core\Form\Form_Choice_Provider_Interface;
/**
 * Class AbstractDatabaseChoiceProvider.
 */
abstract class Abstract_Database_Choice_Provider implements Form_Choice_Provider_Interface
{
    /**
     * @var Connection
     */
    protected $connection;
    /**
     * AbstractDatabaseChoiceProvider constructor.
     *
     * @param string $dbPrefix
     * @param int|null $idLang
     */
    public function __construct(Connection $connection, protected $db_prefix, protected $id_lang = null, protected ?array $shop_ids = null)
    {
        $this->connection = $connection;
    }
    /**
     * {@inheritdoc}
     */
    abstract public function get_choices();
}