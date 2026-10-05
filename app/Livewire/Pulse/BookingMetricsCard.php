<?php

declare(strict_types=1);

namespace App\Livewire\Pulse;

use App\Observability\BookingMetrics;
use Illuminate\Contracts\Support\Renderable;
use Illuminate\Support\Facades\View;
use Laravel\Pulse\Livewire\Card;
use Livewire\Attributes\Lazy;

/**
 * Card do Pulse com as métricas de negócio: conversão do lock, pedidos
 * expirados, estornos por conflito e tempo médio até pagar.
 */
#[Lazy]
class BookingMetricsCard extends Card
{
    public function render(): Renderable
    {
        [$counts, $time, $runAt] = $this->remember(function (): array {
            $counts = [];

            foreach ($this->aggregate(BookingMetrics::TYPE, 'count') as $row) {
                if (is_object($row) && isset($row->key, $row->count)) {
                    $counts[(string) $row->key] = (int) $row->count;
                }
            }

            $timing = $this->aggregate(BookingMetrics::TIME_TO_PAY, ['avg', 'max'])->first();

            return [
                'counts' => $counts,
                'avg_seconds' => is_object($timing) && isset($timing->avg) ? (float) $timing->avg : null,
                'max_seconds' => is_object($timing) && isset($timing->max) ? (float) $timing->max : null,
            ];
        }, 'booking');

        $locks = $counts['counts']['lock_acquired'] ?? 0;
        $paid = $counts['counts']['order_paid'] ?? 0;

        return View::make('livewire.pulse.booking-metrics', [
            'counts' => $counts['counts'],
            'conversion' => $locks > 0 ? round($paid / $locks * 100, 1) : null,
            'avgSeconds' => $counts['avg_seconds'],
            'maxSeconds' => $counts['max_seconds'],
            'time' => $time,
            'runAt' => $runAt,
        ]);
    }
}
