import { useQuery } from '@tanstack/vue-query';
import { computed, toValue, type MaybeRefOrGetter } from 'vue';
import { endpoints } from '@/api/endpoints';

export const seatMapKey = (tripId: string, origin: number, destination: number) => ['seat-map', tripId, origin, destination] as const;

export function useSeatMap(tripId: MaybeRefOrGetter<string>, origin: MaybeRefOrGetter<number>, destination: MaybeRefOrGetter<number>) {
    return useQuery({
        queryKey: computed(() => seatMapKey(toValue(tripId), toValue(origin), toValue(destination))),
        queryFn: async ({ signal }) => (await endpoints.seatMap(toValue(tripId), toValue(origin), toValue(destination), signal)).data,
        // Rede de segurança; as atualizações ao vivo chegam pelo WebSocket.
        refetchInterval: 30_000,
        staleTime: 5_000,
    });
}
