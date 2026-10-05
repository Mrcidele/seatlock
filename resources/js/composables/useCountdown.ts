import { computed, onScopeDispose, ref, toValue, watch, type MaybeRefOrGetter } from 'vue';
import { serverNow } from '@/lib/serverClock';

/**
 * Segundos restantes até expiresAt, medidos pelo relógio do servidor
 * (não pelo relógio local, que pode estar errado).
 */
export function useCountdown(expiresAt: MaybeRefOrGetter<string | null | undefined>, tickMs = 250) {
    const now = ref(serverNow());
    let timer: ReturnType<typeof setInterval> | null = null;

    const target = computed(() => {
        const value = toValue(expiresAt);
        return value ? Date.parse(value) : null;
    });

    const remainingSeconds = computed(() => (target.value === null ? null : Math.max(0, Math.ceil((target.value - now.value) / 1000))));
    const expired = computed(() => remainingSeconds.value === 0);
    const label = computed(() => {
        const s = remainingSeconds.value;
        if (s === null) return '';
        return `${String(Math.floor(s / 60)).padStart(2, '0')}:${String(s % 60).padStart(2, '0')}`;
    });

    function start(): void {
        stop();
        now.value = serverNow();
        timer = setInterval(() => {
            now.value = serverNow();
        }, tickMs);
    }

    function stop(): void {
        if (timer) clearInterval(timer);
        timer = null;
    }

    watch(target, (value) => (value === null ? stop() : start()), { immediate: true });
    onScopeDispose(stop);

    return { remainingSeconds, expired, label };
}
