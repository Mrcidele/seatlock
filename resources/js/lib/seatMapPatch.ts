import type { SeatMap } from '@/api/types';

export type SeatEventName = 'SeatLocked' | 'SeatReleased' | 'SeatSold';

export interface SeatEvent {
    trip_id: string;
    seat_ids: string[];
    origin: number;
    destination: number;
    segments: number[];
}

/**
 * Aplica um evento do canal trip.{id} ao mapa em cache. Só afeta o mapa se
 * o trecho do evento tiver algum segmento em comum com o trecho exibido.
 * Assentos do próprio carrinho (mySeatIds) não são alterados por SeatLocked,
 * já que o evento não diz quem travou.
 *
 * @returns o novo mapa, ou null quando o evento não afeta este trecho
 */
export function applySeatEvent(map: SeatMap, name: SeatEventName, event: SeatEvent, mySeatIds: string[] = []): SeatMap | null {
    const overlaps = event.origin < map.leg.destination && map.leg.origin < event.destination;
    if (event.trip_id !== map.trip_id || !overlaps) return null;

    const ids = new Set(event.seat_ids);
    const summary = { available: 0, locked: 0, sold: 0 };

    const decks = map.decks.map((deck) => ({
        ...deck,
        cells: deck.cells.map((row) =>
            row.map((cell) => {
                const seat = cell.seat;
                if (!seat) return cell;

                let next = seat;
                if (ids.has(seat.id)) {
                    if (name === 'SeatSold') next = { ...seat, status: 'sold', held_by_you: false };
                    else if (name === 'SeatLocked' && seat.status === 'available' && !mySeatIds.includes(seat.id)) next = { ...seat, status: 'locked' };
                    else if (name === 'SeatReleased' && seat.status === 'locked' && !seat.held_by_you) next = { ...seat, status: 'available' };
                }

                summary[next.status]++;
                return next === seat ? cell : { ...cell, seat: next };
            }),
        ),
    }));

    return { ...map, decks, summary };
}
