<?php

declare(strict_types=1);

namespace PrestaShop\PrestaShop\Core\Search\Filters;

use PrestaShop\PrestaShop\Core\Grid\Search\SearchCriteriaInterface;

class LinkBlockFilters implements SearchCriteriaInterface
{
    public function __construct(
        private array $filters = [],
        private string $orderBy = 'lb.id_link_block',
        private string $orderWay = 'ASC',
        private int $offset = 0,
        private int $limit = 0
    ) {
    }

    public function getFilters(): array
    {
        return $this->filters;
    }

    public function getOrderBy(): string
    {
        return $this->orderBy;
    }

    public function getOrderWay(): string
    {
        return $this->orderWay;
    }

    public function getOffset(): int
    {
        return $this->offset;
    }

    public function getLimit(): int
    {
        return $this->limit;
    }
}
