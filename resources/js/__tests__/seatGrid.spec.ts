import { describe, expect, it } from 'vitest';
import { firstSeat, moveInGrid } from '@/lib/seatGrid';
import { deckFrom } from './fixtures';

const deck = deckFrom(['CC_CC', 'CC_CC', 'P._.C']);

describe('moveInGrid', () => {
    it('skips the aisle when moving horizontally', () => {
        expect(moveInGrid(deck, { row: 0, column: 1 }, 'right')).toEqual({ row: 0, column: 3 });
        expect(moveInGrid(deck, { row: 0, column: 3 }, 'left')).toEqual({ row: 0, column: 1 });
    });

    it('stays put at the edges', () => {
        expect(moveInGrid(deck, { row: 0, column: 0 }, 'left')).toEqual({ row: 0, column: 0 });
        expect(moveInGrid(deck, { row: 2, column: 4 }, 'down')).toEqual({ row: 2, column: 4 });
    });

    it('moves vertically to the nearest seat when the column is empty', () => {
        expect(moveInGrid(deck, { row: 1, column: 1 }, 'down')).toEqual({ row: 2, column: 0 });
        expect(moveInGrid(deck, { row: 1, column: 3 }, 'down')).toEqual({ row: 2, column: 4 });
    });

    it('jumps to the first and last seat of the row', () => {
        expect(moveInGrid(deck, { row: 1, column: 3 }, 'home')).toEqual({ row: 1, column: 0 });
        expect(moveInGrid(deck, { row: 1, column: 0 }, 'end')).toEqual({ row: 1, column: 4 });
    });

    it('finds the first seat for the initial focus', () => {
        expect(firstSeat(deckFrom(['._C']))).toEqual({ row: 0, column: 2 });
    });
});
