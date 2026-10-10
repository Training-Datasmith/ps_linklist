<?php

declare(strict_types=1);

namespace PrestaShop\PrestaShop\Adapter\Shop;

class Context
{
    /** @var list<int> */
    private array $shopIds;

    public function __construct(array $shopIds = [1])
    {
        $this->shopIds = array_map('intval', $shopIds);
    }

    public function getShops(bool $active = true, bool $includeInactive = false): array
    {
        return $this->shopIds;
    }

    public function getContextListShopID(): array
    {
        return $this->shopIds;
    }

    public function isAllShopContext(): bool
    {
        return false;
    }
}
