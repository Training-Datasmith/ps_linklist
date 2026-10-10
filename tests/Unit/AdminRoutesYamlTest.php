<?php

declare(strict_types=1);

namespace PrestaShop\Module\LinkList\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Yaml\Yaml;

final class AdminRoutesYamlTest extends TestCase
{
    private array $routes;

    protected function setUp(): void
    {
        parent::setUp();
        $path = dirname(__DIR__, 2) . '/config/routes.yml';
        $this->routes = Yaml::parseFile($path);
    }

    public function testListRouteUsesGet(): void
    {
        self::assertSame(['GET'], $this->routes['admin_link_block_list']['methods']);
    }

    public function testDeleteRouteRequiresNumericId(): void
    {
        self::assertSame('\\d+', $this->routes['admin_link_block_delete']['requirements']['linkBlockId']);
    }

    public function testUpdatePositionsRouteTargetsHookId(): void
    {
        self::assertStringContainsString('updatePositionsAction', $this->routes['admin_link_block_update_positions']['defaults']['_controller']);
        self::assertSame('\\d+', $this->routes['admin_link_block_update_positions']['requirements']['hookId']);
    }
}
