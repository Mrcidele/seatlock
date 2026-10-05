import type { Deck, SeatCell } from '@/api/types';

export type Direction = 'up' | 'down' | 'left' | 'right' | 'home' | 'end';

export interface Position {
    row: number;
    column: number;
}

const isSeat = (cell: SeatCell | undefined): boolean => cell?.kind === 'seat' && cell.seat !== null;

export function firstSeat(deck: Deck): Position | null {
    for (const row of deck.cells) {
        for (const cell of row) {
            if (isSeat(cell)) return { row: cell.row, column: cell.column };
        }
    }
    return null;
}

/**
 * Próximo assento na direção indicada, pulando corredor e células vazias.
 * Subindo/descendo, se a mesma coluna não tiver assento, escolhe o assento
 * mais próximo naquela fileira. Retorna a posição atual se não houver para onde ir.
 */
export function moveInGrid(deck: Deck, from: Position, direction: Direction): Position {
    const row = deck.cells[from.row] ?? [];

    switch (direction) {
        case 'left':
            for (let c = from.column - 1; c >= 0; c--) if (isSeat(row[c])) return { row: from.row, column: c };
            return from;
        case 'right':
            for (let c = from.column + 1; c < row.length; c++) if (isSeat(row[c])) return { row: from.row, column: c };
            return from;
        case 'home':
            for (let c = 0; c < row.length; c++) if (isSeat(row[c])) return { row: from.row, column: c };
            return from;
        case 'end':
            for (let c = row.length - 1; c >= 0; c--) if (isSeat(row[c])) return { row: from.row, column: c };
            return from;
        case 'up':
        case 'down': {
            const step = direction === 'up' ? -1 : 1;
            for (let r = from.row + step; r >= 0 && r < deck.cells.length; r += step) {
                const candidate = nearestSeatInRow(deck.cells[r] ?? [], from.column);
                if (candidate !== null) return { row: r, column: candidate };
            }
            return from;
        }
    }
}

function nearestSeatInRow(row: SeatCell[], column: number): number | null {
    let best: number | null = null;
    for (let c = 0; c < row.length; c++) {
        if (isSeat(row[c]) && (best === null || Math.abs(c - column) < Math.abs(best - column))) best = c;
    }
    return best;
}

export function directionFromKey(key: string): Direction | null {
    return (
        ({ ArrowUp: 'up', ArrowDown: 'down', ArrowLeft: 'left', ArrowRight: 'right', Home: 'home', End: 'end' } as const)[key] ?? null
    );
}
