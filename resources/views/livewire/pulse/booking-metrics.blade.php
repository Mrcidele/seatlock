@php
    $metrics = [
        ['Locks adquiridos', $counts['lock_acquired'] ?? 0],
        ['Conflitos de lock', $counts['lock_conflict'] ?? 0],
        ['Pedidos criados', $counts['order_created'] ?? 0],
        ['Pedidos pagos', $counts['order_paid'] ?? 0],
        ['Pedidos expirados', $counts['order_expired'] ?? 0],
        ['Estornos por conflito', $counts['seats_taken_refund'] ?? 0],
        ['Locks em modo degradado', $counts['lock_degraded'] ?? 0],
    ];
    $fmt = fn (?float $s) => $s === null ? '—' : sprintf('%dm%02ds', intdiv((int) $s, 60), (int) $s % 60);
@endphp
<x-pulse::card :cols="$cols" :rows="$rows" :class="$class">
    <x-pulse::card-header name="Reservas" title="Tempo: {{ number_format($time) }}ms; executado em: {{ $runAt }};" details="últimos {{ $this->periodForHumans() }}">
        <x-slot:icon>
            <x-pulse::icons.sparkles />
        </x-slot:icon>
    </x-pulse::card-header>

    <x-pulse::scroll :expand="$expand" wire:poll.5s="">
        <div class="grid grid-cols-3 gap-3 text-center mb-4">
            <div>
                <span class="text-xl font-bold text-gray-700 dark:text-gray-300 tabular-nums">{{ $conversion === null ? '—' : $conversion.'%' }}</span>
                <span class="block text-xs text-gray-500">conversão do lock</span>
            </div>
            <div>
                <span class="text-xl font-bold text-gray-700 dark:text-gray-300 tabular-nums">{{ $fmt($avgSeconds) }}</span>
                <span class="block text-xs text-gray-500">tempo médio até pagar</span>
            </div>
            <div>
                <span class="text-xl font-bold text-gray-700 dark:text-gray-300 tabular-nums">{{ $fmt($maxSeconds) }}</span>
                <span class="block text-xs text-gray-500">máximo até pagar</span>
            </div>
        </div>
        <x-pulse::table>
            <tbody>
                @foreach ($metrics as [$label, $value])
                    <tr class="h-2 first:h-0"></tr>
                    <tr wire:key="{{ $label }}">
                        <x-pulse::td class="text-gray-700 dark:text-gray-300">{{ $label }}</x-pulse::td>
                        <x-pulse::td numeric class="text-gray-700 dark:text-gray-300 font-bold">{{ number_format($value) }}</x-pulse::td>
                    </tr>
                @endforeach
            </tbody>
        </x-pulse::table>
    </x-pulse::scroll>
</x-pulse::card>
