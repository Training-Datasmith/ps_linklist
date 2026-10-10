<?php

declare(strict_types=1);

namespace PrestaShop\PrestaShop\Core\Grid\Query;

use Doctrine\DBAL\Connection;

abstract class AbstractDoctrineQueryBuilder
{
    protected Connection $connection;

    protected string $dbPrefix;

    public function __construct(Connection $connection, string $dbPrefix)
    {
        $this->connection = $connection;
        $this->dbPrefix = $dbPrefix;
    }
}
