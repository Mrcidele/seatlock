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
        [$data, $time, $runAt] = $this->remember(fn (): array => [
            'counts' => $this->counts(),
            'timing' => $this->aggregate(BookingMetrics::TIME_TO_PAY, ['avg', 'max'])->first(),
        ], 'booking');

        $counts = is_array($data) && is_array($data['counts'] ?? null) ? $data['counts'] : [];
        $timing = is_array($data) ? ($data['timing'] ?? null) : null;

        $locks = self::number($counts['lock_acquired'] ?? 0);
        $paid = self::number($counts['order_paid'] ?? 0);

        return View::make('livewire.pulse.booking-metrics', [
            'counts' => $counts,
            'conversion' => $locks > 0 ? round($paid / $locks * 100, 1) : null,
            'avgSeconds' => is_object($timing) && isset($timing->avg) ? self::number($timing->avg) : null,
            'maxSeconds' => is_object($timing) && isset($timing->max) ? self::number($timing->max) : null,
            'time' => $time,
            'runAt' => $runAt,
        ]);
    }

    /**
     * @return array<string, int>
     */
    private function counts(): array
    {
        $counts = [];

        foreach ($this->aggregate(BookingMetrics::TYPE, 'count') as $row) {
            if (is_object($row) && isset($row->key, $row->count) && is_string($row->key)) {
                $counts[$row->key] = (int) self::number($row->count);
            }
        }

        return $counts;
    }

    private static function number(mixed $value): float
    {
        return is_numeric($value) ? (float) $value : 0.0;
    }
}
