<?php

declare(strict_types=1);

use App\Enums\SeatType;
use App\Layout\CellKind;
use App\Layout\InvalidLayout;
use App\Layout\LayoutPresets;
use App\Layout\SeatLayout;

it('parses decks, rows, aisle and seat types', function (): void {
    $layout = SeatLayout::fromArray([
        'decks' => [
            ['level' => 1, 'rows' => ['LL_L', 'P._W']],
            ['level' => 2, 'rows' => ['CC_CC']],
        ],
    ]);

    expect($layout->decks)->toHaveCount(2)
        ->and($layout->seatCount())->toBe(8)
        ->and($layout->decks[0]->rows[0][2]->kind)->toBe(CellKind::Aisle)
        ->and($layout->decks[0]->rows[1][3]->kind)->toBe(CellKind::Toilet)
        ->and($layout->decks[0]->rows[1][0]->seatType)->toBe(SeatType::Accessible)
        ->and($layout->decks[0]->rows[0][0]->seatType)->toBe(SeatType::Sleeper);
});

it('numbers seats in reading order across decks', function (): void {
    $numbers = array_map(
        fn ($cell) => $cell->seatNumber,
        SeatLayout::fromArray(['decks' => [['rows' => ['C_C']], ['rows' => ['CC']]]])->seats(),
    );

    expect($numbers)->toBe(['01', '02', '03', '04']);
});

it('rejects malformed layouts', function (array $layout): void {
    SeatLayout::fromArray($layout);
})->with([
    'no decks' => [[]],
    'ragged rows' => [['decks' => [['rows' => ['CC_C', 'CC']]]]],
    'unknown code' => [['decks' => [['rows' => ['CX']]]]],
    'no seats' => [['decks' => [['rows' => ['_._']]]]],
    'duplicated level' => [['decks' => [['level' => 1, 'rows' => ['C']], ['level' => 1, 'rows' => ['C']]]]],
])->throws(InvalidLayout::class);

it('ships valid presets', function (): void {
    expect(SeatLayout::fromArray(LayoutPresets::doubleDecker())->seatCount())->toBe(67)
        ->and(SeatLayout::fromArray(LayoutPresets::conventional())->seatCount())->toBe(45);
});
