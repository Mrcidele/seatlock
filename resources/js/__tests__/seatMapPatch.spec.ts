import { describe, expect, it } from 'vitest';
import { applySeatEvent, type SeatEvent } from '@/lib/seatMapPatch';
import { seatMapFrom } from './fixtures';

const event = (seatIds: string[], origin = 0, destination = 2): SeatEvent => ({
    trip_id: 'trip-1',
    seat_ids: seatIds,
    origin,
    destination,
    segments: [],
});

const statusOf = (map: ReturnType<typeof seatMapFrom>, id: string) =>
    map.decks.flatMap((d) => d.cells.flat()).find((c) => c.seat?.id === id)?.seat?.status;

describe('applySeatEvent', () => {
    it('marks seats sold and locked on overlapping legs', () => {
        const map = seatMapFrom(['CC_CC']); // trecho exibido: 0 -> 2

        const sold = applySeatEvent(map, 'SeatSold', event(['seat-1'], 1, 3))!;
        const locked = applySeatEvent(sold, 'SeatLocked', event(['seat-2'], 0, 1))!;

        expect(statusOf(locked, 'seat-1')).toBe('sold');
        expect(statusOf(locked, 'seat-2')).toBe('locked');
        expect(locked.summary).toEqual({ available: 2, locked: 1, sold: 1 });
    });

    it('ignores events for legs that do not share a segment', () => {
        expect(applySeatEvent(seatMapFrom(['CC']), 'SeatSold', event(['seat-1'], 2, 3))).toBeNull();
    });

    it('does not flag my own seats as locked by someone else', () => {
        const patched = applySeatEvent(seatMapFrom(['CC']), 'SeatLocked', event(['seat-1']), ['seat-1'])!;

        expect(statusOf(patched, 'seat-1')).toBe('available');
    });

    it('frees a seat released by another cart', () => {
        const locked = applySeatEvent(seatMapFrom(['CC']), 'SeatLocked', event(['seat-1']))!;
        const released = applySeatEvent(locked, 'SeatReleased', event(['seat-1']))!;

        expect(statusOf(released, 'seat-1')).toBe('available');
    });
});
