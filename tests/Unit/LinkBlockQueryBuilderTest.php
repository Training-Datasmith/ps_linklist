<?php

declare(strict_types=1);

namespace PrestaShop\Module\LinkList\Tests\Unit;

use PrestaShop\Module\LinkList\Core\Grid\Query\LinkBlockQueryBuilder;
use PrestaShop\Module\LinkList\Tests\Support\DatabaseTestCase;

final class LinkBlockQueryBuilderTest extends DatabaseTestCase
{
    public function testCountQueryBuilderReturnsDistinctBlocks(): void
    {
        $this->connection->insert(_DB_PREFIX_ . 'link_block', [
            'id_hook' => 1,
            'position' => 0,
            'content' => '{}',
        ]);
        $blockId = (int) $this->connection->lastInsertId();
        $this->connection->insert(_DB_PREFIX_ . 'link_block_lang', [
            'id_link_block' => $blockId,
            'id_lang' => 1,
            'name' => 'Footer links',
        ]);
        $this->connection->insert(_DB_PREFIX_ . 'link_block_shop', [
            'id_link_block' => $blockId,
            'id_shop' => 1,
            'position' => 0,
        ]);
        $this->connection->insert(_DB_PREFIX_ . 'link_block_shop', [
            'id_link_block' => $blockId,
            'id_shop' => 2,
            'position' => 0,
        ]);

        $builder = new LinkBlockQueryBuilder($this->connection, _DB_PREFIX_);
        $count = (int) $builder->getCountQueryBuilder($this->makeSearchCriteria(['id_lang' => 1]))->execute()->fetchOne();

        self::assertSame(1, $count);
    }
}
