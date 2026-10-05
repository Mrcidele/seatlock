<?php

declare(strict_types=1);

namespace App\Layout;

final readonly class DeckLayout
{
    /**
     * @param  list<list<LayoutCell>>  $rows
     */
    public function __construct(
        public int $level,
        public array $rows,
        public int $columns,
    ) {}

    public function rowCount(): int
    {
        return count($this->rows);
    }
}
