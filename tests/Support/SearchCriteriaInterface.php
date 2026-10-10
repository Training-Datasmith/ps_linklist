<?php

declare(strict_types=1);

namespace PrestaShop\PrestaShop\Core\Grid\Search;

interface SearchCriteriaInterface
{
    public function getFilters(): array;

    public function getOrderBy(): string;

    public function getOrderWay(): string;

    public function getOffset(): int;

    public function getLimit(): int;
}
