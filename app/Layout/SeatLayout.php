<?php

declare(strict_types=1);

namespace App\Layout;

use App\Enums\SeatType;

/**
 * Layout de um veículo descrito em JSON:
 *
 *   {"decks": [{"level": 1, "rows": ["LL_L", "LL_L", "P._W"]}]}
 *
 * Cada caractere de uma fileira é uma célula:
 *   C = convencional, L = leito, P = PCD, _ = corredor, . = vazio,
 *   W = banheiro, E = escada.
 *
 * Os assentos são numerados em ordem de leitura (andar, fileira, coluna).
 */
final readonly class SeatLayout
{
    /**
     * @param  list<DeckLayout>  $decks
     */
    private function __construct(public array $decks) {}

    /**
     * @param  array<mixed>  $layout
     *
     * @throws InvalidLayout
     */
    public static function fromArray(array $layout): self
    {
        $decks = $layout['decks'] ?? null;

        if (! is_array($decks) || $decks === []) {
            throw new InvalidLayout('O layout precisa ter ao menos um andar em "decks".');
        }

        $parsed = [];
        $levels = [];
        $seatCounter = 0;

        foreach (array_values($decks) as $index => $deck) {
            if (! is_array($deck)) {
                throw new InvalidLayout("Andar #{$index} inválido.");
            }

            $level = $deck['level'] ?? $index + 1;
            $rows = $deck['rows'] ?? null;

            if (! is_int($level) || $level < 1) {
                throw new InvalidLayout("Andar #{$index}: \"level\" precisa ser inteiro positivo.");
            }

            if (in_array($level, $levels, true)) {
                throw new InvalidLayout("Andar {$level} repetido.");
            }

            if (! is_array($rows) || $rows === []) {
                throw new InvalidLayout("Andar {$level}: \"rows\" precisa ser uma lista não vazia.");
            }

            $levels[] = $level;
            $width = null;
            $cells = [];

            foreach (array_values($rows) as $rowIndex => $row) {
                if (! is_string($row) || $row === '') {
                    throw new InvalidLayout("Andar {$level}, fileira {$rowIndex}: precisa ser texto não vazio.");
                }

                $width ??= strlen($row);

                if (strlen($row) !== $width) {
                    throw new InvalidLayout("Andar {$level}, fileira {$rowIndex}: todas as fileiras devem ter {$width} colunas.");
                }

                $rowCells = [];

                foreach (str_split($row) as $column => $code) {
                    $kind = CellKind::fromCode($code);

                    if ($kind === null) {
                        throw new InvalidLayout("Andar {$level}, fileira {$rowIndex}, coluna {$column}: código \"{$code}\" desconhecido.");
                    }

                    $type = SeatType::fromLayoutCode($code);
                    $number = $type !== null ? str_pad((string) ++$seatCounter, 2, '0', STR_PAD_LEFT) : null;

                    $rowCells[] = new LayoutCell($level, $rowIndex, $column, $kind, $type, $number);
                }

                $cells[] = $rowCells;
            }

            $parsed[] = new DeckLayout($level, $cells, (int) $width);
        }

        if ($seatCounter === 0) {
            throw new InvalidLayout('O layout não tem nenhum assento.');
        }

        return new self($parsed);
    }

    /**
     * @return list<LayoutCell>
     */
    public function seats(): array
    {
        $seats = [];

        foreach ($this->decks as $deck) {
            foreach ($deck->rows as $row) {
                foreach ($row as $cell) {
                    if ($cell->kind === CellKind::Seat) {
                        $seats[] = $cell;
                    }
                }
            }
        }

        return $seats;
    }

    public function seatCount(): int
    {
        return count($this->seats());
    }
}
