import type { Deck, SeatCell, SeatMap, SeatInfo } from '@/api/types';

/** "CC_CC" / "LP_.C" -> células como a API devolve. */
export function deckFrom(rows: string[], level = 1): Deck {
    let counter = 0;
    const cells: SeatCell[][] = rows.map((row, r) =>
        [...row].map((code, c): SeatCell => {
            if (!'CLP'.includes(code)) {
                return { row: r, column: c, kind: code === '_' ? 'aisle' : 'empty', seat: null };
            }
            counter++;
            const seat: SeatInfo = {
                id: `seat-${counter}`,
                number: String(counter).padStart(2, '0'),
                type: code === 'L' ? 'sleeper' : code === 'P' ? 'accessible' : 'conventional',
                type_label: code === 'L' ? 'Leito' : code === 'P' ? 'PCD' : 'Convencional',
                status: 'available',
                held_by_you: false,
                price: { cents: 6000, currency: 'BRL', formatted: 'R$ 60,00' },
            };
            return { row: r, column: c, kind: 'seat', seat };
        }),
    );

    return { level, rows: rows.length, columns: rows[0]?.length ?? 0, cells };
}

export function seatMapFrom(rows: string[]): SeatMap {
    return {
        trip_id: 'trip-1',
        leg: { origin: 0, destination: 2, segments: [0, 1] },
        decks: [deckFrom(rows)],
        summary: { available: 0, locked: 0, sold: 0 },
    };
}
