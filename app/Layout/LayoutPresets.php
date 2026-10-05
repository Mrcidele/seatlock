<?php

declare(strict_types=1);

namespace App\Layout;

final class LayoutPresets
{
    /**
     * Ônibus convencional de um andar: 44 convencionais + 1 PCD.
     *
     * @return array{decks: list<array{level: int, rows: list<string>}>}
     */
    public static function conventional(): array
    {
        return [
            'decks' => [
                ['level' => 1, 'rows' => [...array_fill(0, 11, 'CC_CC'), 'P._.W']],
            ],
        ];
    }

    /**
     * Double decker: leito embaixo (com PCD e escada), convencional em cima.
     *
     * @return array{decks: list<array{level: int, rows: list<string>}>}
     */
    public static function doubleDecker(): array
    {
        return [
            'decks' => [
                ['level' => 1, 'rows' => [...array_fill(0, 6, 'LL_L'), 'P_.E']],
                ['level' => 2, 'rows' => [...array_fill(0, 12, 'CC_CC'), '.._.W']],
            ],
        ];
    }

    /**
     * Layout pequeno usado nos testes: 12 assentos.
     *
     * @return array{decks: list<array{level: int, rows: list<string>}>}
     */
    public static function compact(): array
    {
        return [
            'decks' => [
                ['level' => 1, 'rows' => ['CC_CC', 'CC_CC', 'LP_CC']],
            ],
        ];
    }
}
