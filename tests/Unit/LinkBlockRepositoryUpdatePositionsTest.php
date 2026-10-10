<?php

declare(strict_types=1);

namespace PrestaShop\Module\LinkList\Tests\Unit;

use PrestaShop\Module\LinkList\Tests\Support\DatabaseTestCase;
use PrestaShop\PrestaShop\Core\Exception\DatabaseException;

final class LinkBlockRepositoryUpdatePositionsTest extends DatabaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->connection->executeStatement('DROP TRIGGER IF EXISTS ps_link_block_shop_fail_second_update');
        $this->connection->executeStatement(
            'CREATE TRIGGER ps_link_block_shop_fail_second_update BEFORE UPDATE ON ps_link_block_shop
            FOR EACH ROW
            BEGIN
                IF @fail_second_position_update = 1 THEN
                    SET @position_update_count = IFNULL(@position_update_count, 0) + 1;
                    IF @position_update_count > 1 THEN
                        SIGNAL SQLSTATE \'45000\' SET MESSAGE_TEXT = \'forced failure\';
                    END IF;
                END IF;
            END'
        );
    }

    protected function tearDown(): void
    {
        $this->connection->executeStatement('DROP TRIGGER IF EXISTS ps_link_block_shop_fail_second_update');
        parent::tearDown();
    }

    public function testUpdatePositionsRollsBackOnFailure(): void
    {
        $this->seedLinkBlockShopRows(1, [10 => 1, 20 => 0]);
        $before = $this->fetchPositions(1);
        $repository = $this->createLinkBlockRepository();

        $this->connection->executeStatement('SET @fail_second_position_update = 1');
        $this->connection->executeStatement('SET @position_update_count = 0');

        try {
            $repository->updatePositions(1, [
                'positions' => [
                    ['rowId' => 10],
                    ['rowId' => 20],
                ],
            ]);
            self::fail('Expected DatabaseException');
        } catch (DatabaseException $e) {
            self::assertMatchesRegularExpression('/#20$/', $e->getMessage());
            self::assertNotNull($e->getPrevious());
        } finally {
            $this->connection->executeStatement('SET @fail_second_position_update = 0');
        }

        self::assertFalse($this->connection->isTransactionActive());
        self::assertSame($before, $this->fetchPositions(1));
    }
}
