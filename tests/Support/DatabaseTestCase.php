<?php

declare(strict_types=1);

namespace PrestaShop\Module\LinkList\Tests\Support;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use PHPUnit\Framework\TestCase;
use PrestaShop\Module\LinkList\Adapter\ObjectModelHandler;
use PrestaShop\Module\LinkList\Repository\LinkBlockRepository;
use PrestaShop\PrestaShop\Adapter\Shop\Context as ShopContext;
use PrestaShop\PrestaShop\Core\Grid\Search\SearchCriteriaInterface;
use Symfony\Component\Translation\Loader\ArrayLoader;
use Symfony\Component\Translation\Translator;

abstract class DatabaseTestCase extends TestCase
{
    protected static ?Connection $sharedConnection = null;

    protected Connection $connection;

    protected function setUp(): void
    {
        parent::setUp();
        $this->connection = self::getSharedConnection();
        $this->connection->executeStatement('SET FOREIGN_KEY_CHECKS=0');
        $this->resetLinkBlockTables();
        $this->connection->executeStatement('SET FOREIGN_KEY_CHECKS=1');
    }

    public static function getSharedConnection(): Connection
    {
        if (self::$sharedConnection === null) {
            $params = [
                'dbname' => getenv('TEST_MYSQL_DATABASE') ?: 'linklist_test',
                'user' => getenv('TEST_MYSQL_USER') ?: 'root',
                'password' => getenv('TEST_MYSQL_PASSWORD') ?: 'root',
                'host' => getenv('TEST_MYSQL_HOST') ?: '127.0.0.1',
                'port' => (int) (getenv('TEST_MYSQL_PORT') ?: 3306),
                'driver' => 'pdo_mysql',
                'charset' => 'utf8mb4',
            ];
            self::$sharedConnection = DriverManager::getConnection($params);
            self::$sharedConnection->executeStatement(
                "SET sql_mode=(SELECT REPLACE(@@sql_mode,'ONLY_FULL_GROUP_BY',''))"
            );
            self::ensureCatalogTables(self::$sharedConnection);
        }

        return self::$sharedConnection;
    }

    protected function resetLinkBlockTables(): void
    {
        foreach (['link_block_shop', 'link_block_lang', 'link_block'] as $table) {
            $this->connection->executeStatement('DELETE FROM ' . _DB_PREFIX_ . $table);
        }
    }

    protected function createLinkBlockRepository(?ShopContext $shopContext = null): LinkBlockRepository
    {
        $translator = new Translator('en');
        $translator->addLoader('array', new ArrayLoader());
        $translator->addResource('array', ['Products' => 'Products', 'Our company' => 'Our company'], 'en');

        return new LinkBlockRepository(
            $this->connection,
            _DB_PREFIX_,
            [['id_lang' => 1, 'locale' => 'en-US']],
            $translator,
            false,
            $shopContext ?? new ShopContext([1, 2]),
            new ObjectModelHandler()
        );
    }

    protected function seedLinkBlockShopRows(int $shopId, array $rowIdsWithPositions): void
    {
        foreach ($rowIdsWithPositions as $rowId => $position) {
            $this->connection->insert(_DB_PREFIX_ . 'link_block_shop', [
                'id_link_block' => (int) $rowId,
                'id_shop' => $shopId,
                'position' => (int) $position,
            ]);
        }
    }

    protected function fetchPositions(int $shopId): array
    {
        $rows = $this->connection->fetchAllAssociative(
            'SELECT id_link_block, position FROM ' . _DB_PREFIX_ . 'link_block_shop WHERE id_shop = ? ORDER BY id_link_block',
            [$shopId]
        );
        $out = [];
        foreach ($rows as $row) {
            $out[(int) $row['id_link_block']] = (int) $row['position'];
        }

        return $out;
    }

    protected function makeSearchCriteria(array $filters = []): SearchCriteriaInterface
    {
        return new class ($filters) implements SearchCriteriaInterface {
            public function __construct(private array $filters)
            {
            }

            public function getFilters(): array
            {
                return $this->filters;
            }

            public function getOrderBy(): string
            {
                return 'lb.id_link_block';
            }

            public function getOrderWay(): string
            {
                return 'ASC';
            }

            public function getOffset(): int
            {
                return 0;
            }

            public function getLimit(): int
            {
                return 0;
            }
        };
    }

    private static function ensureCatalogTables(Connection $connection): void
    {
        $ddl = [
            'CREATE TABLE IF NOT EXISTS ps_shop (id_shop INT UNSIGNED NOT NULL AUTO_INCREMENT, name VARCHAR(64) NOT NULL, PRIMARY KEY (id_shop)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4',
            'CREATE TABLE IF NOT EXISTS ps_hook (id_hook INT UNSIGNED NOT NULL AUTO_INCREMENT, name VARCHAR(64) NOT NULL, title VARCHAR(64) NOT NULL DEFAULT "", description TEXT NULL, PRIMARY KEY (id_hook)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4',
            'CREATE TABLE IF NOT EXISTS ps_category (id_category INT UNSIGNED NOT NULL AUTO_INCREMENT, active TINYINT(1) NOT NULL DEFAULT 1, PRIMARY KEY (id_category)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4',
            'CREATE TABLE IF NOT EXISTS ps_category_lang (id_category INT UNSIGNED NOT NULL, id_lang INT UNSIGNED NOT NULL, name VARCHAR(128) NOT NULL, PRIMARY KEY (id_category, id_lang)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4',
            'CREATE TABLE IF NOT EXISTS ps_category_shop (id_category INT UNSIGNED NOT NULL, id_shop INT UNSIGNED NOT NULL, PRIMARY KEY (id_category, id_shop)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4',
            'CREATE TABLE IF NOT EXISTS ps_cms_category (id_cms_category INT UNSIGNED NOT NULL AUTO_INCREMENT, active TINYINT(1) NOT NULL DEFAULT 1, PRIMARY KEY (id_cms_category)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4',
            'CREATE TABLE IF NOT EXISTS ps_cms_category_lang (id_cms_category INT UNSIGNED NOT NULL, id_lang INT UNSIGNED NOT NULL, name VARCHAR(128) NOT NULL, PRIMARY KEY (id_cms_category, id_lang)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4',
            'CREATE TABLE IF NOT EXISTS ps_cms_category_shop (id_cms_category INT UNSIGNED NOT NULL, id_shop INT UNSIGNED NOT NULL, PRIMARY KEY (id_cms_category, id_shop)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4',
            'CREATE TABLE IF NOT EXISTS ps_cms (id_cms INT UNSIGNED NOT NULL AUTO_INCREMENT, active TINYINT(1) NOT NULL DEFAULT 1, id_cms_category INT UNSIGNED NOT NULL, position INT UNSIGNED NOT NULL DEFAULT 0, PRIMARY KEY (id_cms)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4',
            'CREATE TABLE IF NOT EXISTS ps_cms_lang (id_cms INT UNSIGNED NOT NULL, id_lang INT UNSIGNED NOT NULL, meta_title VARCHAR(128) NOT NULL, PRIMARY KEY (id_cms, id_lang)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4',
            'CREATE TABLE IF NOT EXISTS ps_cms_shop (id_cms INT UNSIGNED NOT NULL, id_shop INT UNSIGNED NOT NULL, PRIMARY KEY (id_cms, id_shop)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4',
            'CREATE TABLE IF NOT EXISTS ps_meta (id_meta INT UNSIGNED NOT NULL AUTO_INCREMENT, page VARCHAR(64) NOT NULL, PRIMARY KEY (id_meta)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4',
            'CREATE TABLE IF NOT EXISTS ps_meta_lang (id_meta INT UNSIGNED NOT NULL, id_lang INT UNSIGNED NOT NULL, id_shop INT UNSIGNED NOT NULL, title VARCHAR(128) NOT NULL, PRIMARY KEY (id_meta, id_lang, id_shop)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4',
            'CREATE TABLE IF NOT EXISTS ps_link_block (id_link_block INT UNSIGNED NOT NULL AUTO_INCREMENT, id_hook INT UNSIGNED NULL, position INT UNSIGNED NOT NULL DEFAULT 0, content TEXT NULL, PRIMARY KEY (id_link_block)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4',
            'CREATE TABLE IF NOT EXISTS ps_link_block_lang (id_link_block INT UNSIGNED NOT NULL, id_lang INT UNSIGNED NOT NULL, name VARCHAR(40) NOT NULL DEFAULT "", custom_content TEXT NULL, PRIMARY KEY (id_link_block, id_lang)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4',
            'CREATE TABLE IF NOT EXISTS ps_link_block_shop (id_link_block INT UNSIGNED NOT NULL, id_shop INT UNSIGNED NOT NULL, position INT UNSIGNED NOT NULL DEFAULT 0, PRIMARY KEY (id_link_block, id_shop)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4',
        ];

        foreach ($ddl as $sql) {
            $connection->executeStatement($sql);
        }

        $connection->executeStatement('DELETE FROM ps_shop');
        $connection->executeStatement('INSERT INTO ps_shop (id_shop, name) VALUES (1, "Shop 1"), (2, "Shop 2")');
        $connection->executeStatement('DELETE FROM ps_hook');
        $connection->executeStatement('INSERT INTO ps_hook (id_hook, name, title) VALUES (1, "displayFooter", "Footer")');
        \Hook::setHook('displayFooter', 1);

        self::seedChoiceCatalog($connection);
    }

    private static function seedChoiceCatalog(Connection $connection): void
    {
        $connection->executeStatement('DELETE FROM ps_category_shop');
        $connection->executeStatement('DELETE FROM ps_category_lang');
        $connection->executeStatement('DELETE FROM ps_category');
        $connection->executeStatement('INSERT INTO ps_category (id_category, active) VALUES (10, 1), (11, 1)');
        $connection->executeStatement('INSERT INTO ps_category_lang (id_category, id_lang, name) VALUES (10, 1, "Cat A"), (11, 1, "Cat B")');
        $connection->executeStatement('INSERT INTO ps_category_shop (id_category, id_shop) VALUES (10, 1), (11, 2)');

        $connection->executeStatement('DELETE FROM ps_cms_category_shop');
        $connection->executeStatement('DELETE FROM ps_cms_category_lang');
        $connection->executeStatement('DELETE FROM ps_cms_category');
        $connection->executeStatement('INSERT INTO ps_cms_category (id_cms_category, active) VALUES (5, 1), (6, 1)');
        $connection->executeStatement('INSERT INTO ps_cms_category_lang (id_cms_category, id_lang, name) VALUES (5, 1, "CMS root"), (6, 1, "CMS shop2 only")');
        $connection->executeStatement('INSERT INTO ps_cms_category_shop (id_cms_category, id_shop) VALUES (5, 1), (6, 2)');

        $connection->executeStatement('DELETE FROM ps_cms_shop');
        $connection->executeStatement('DELETE FROM ps_cms_lang');
        $connection->executeStatement('DELETE FROM ps_cms');
        $connection->executeStatement('INSERT INTO ps_cms (id_cms, active, id_cms_category, position) VALUES (20, 1, 5, 0)');
        $connection->executeStatement('INSERT INTO ps_cms_lang (id_cms, id_lang, meta_title) VALUES (20, 1, "About us")');
        $connection->executeStatement('INSERT INTO ps_cms_shop (id_cms, id_shop) VALUES (20, 2)');

        $connection->executeStatement('DELETE FROM ps_meta_lang');
        $connection->executeStatement('DELETE FROM ps_meta');
        $connection->executeStatement('INSERT INTO ps_meta (id_meta, page) VALUES (1, "contact")');
        $connection->executeStatement('INSERT INTO ps_meta_lang (id_meta, id_lang, id_shop, title) VALUES (1, 1, 1, "Contact page")');
    }
}
