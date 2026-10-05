import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { effectScope, nextTick, ref } from 'vue';
import { useCountdown } from '@/composables/useCountdown';
import { resetClock, serverNow, syncWithServer } from '@/lib/serverClock';

describe('server clock', () => {
    beforeEach(() => {
        vi.useFakeTimers();
        vi.setSystemTime(new Date('2026-10-05T12:00:00Z'));
        resetClock();
    });

    afterEach(() => vi.useRealTimers());

    it('corrects the local clock with the server time minus half the round trip', () => {
        // Relógio local 2 minutos atrasado; requisição levou 200 ms.
        const startedAt = Date.now() - 200;
        syncWithServer('2026-10-05T12:02:00Z', startedAt, Date.now());

        expect(serverNow() - Date.now()).toBe(120_000 + 100);
    });

    it('counts down against the server expiry, not the local clock', async () => {
        syncWithServer('2026-10-05T12:05:00Z', Date.now(), Date.now()); // local atrasado 5 min
        const expiresAt = ref<string | null>('2026-10-05T12:15:00Z');

        const scope = effectScope();
        const countdown = scope.run(() => useCountdown(expiresAt))!;

        expect(countdown.remainingSeconds.value).toBe(600);
        expect(countdown.label.value).toBe('10:00');

        vi.advanceTimersByTime(599_000);
        await nextTick();
        expect(countdown.label.value).toBe('00:01');

        vi.advanceTimersByTime(2_000);
        await nextTick();
        expect(countdown.expired.value).toBe(true);

        scope.stop();
    });
});
