<?php

declare(strict_types=1);

namespace PrestaShop\Module\LinkList\Tests\Unit;

use PrestaShop\Module\LinkList\Tests\Support\DatabaseTestCase;

final class LinkBlockRepositorySchemaTest extends DatabaseTestCase
{
    public function testCreateTablesReturnsNoErrors(): void
    {
        $repository = $this->createLinkBlockRepository();

        self::assertSame([], $repository->createTables());
    }
}
