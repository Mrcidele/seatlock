import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';
import SeatMapView from '@/components/SeatMapView.vue';
import { seatMapFrom } from './fixtures';

describe('SeatMapView', () => {
    const seatMap = seatMapFrom(['CC_CC', 'CC_CC']);
    seatMap.decks[0]!.cells[0]![1]!.seat!.status = 'sold';
    seatMap.decks[0]!.cells[0]![3]!.seat!.status = 'locked';

    const mountMap = () => mount(SeatMapView, { props: { seatMap, selectedIds: [], pendingId: null }, attachTo: document.body });

    it('exposes an accessible grid with one tabbable seat', () => {
        const wrapper = mountMap();

        expect(wrapper.find('[role="grid"]').exists()).toBe(true);
        expect(wrapper.findAll('button[tabindex="0"]')).toHaveLength(1);
        expect(wrapper.get('[data-seat="01"]').attributes('aria-label')).toContain('Assento 01, Convencional, disponível');
        expect(wrapper.get('[data-seat="02"]').attributes('aria-disabled')).toBe('true');
        wrapper.unmount();
    });

    it('moves focus with arrow keys skipping the aisle', async () => {
        const wrapper = mountMap();

        await wrapper.get('[data-seat="02"]').trigger('keydown', { key: 'ArrowRight' });

        expect(document.activeElement?.getAttribute('data-seat')).toBe('03');
        expect(wrapper.get('[data-seat="03"]').attributes('tabindex')).toBe('0');
        wrapper.unmount();
    });

    it('emits toggle only for seats that can be selected', async () => {
        const wrapper = mountMap();

        await wrapper.get('[data-seat="01"]').trigger('click');
        await wrapper.get('[data-seat="02"]').trigger('click'); // vendido
        await wrapper.get('[data-seat="03"]').trigger('click'); // travado por outro

        expect(wrapper.emitted('toggle')).toHaveLength(1);
        expect(wrapper.emitted('toggle')![0]![0]).toMatchObject({ number: '01' });
        wrapper.unmount();
    });
});
