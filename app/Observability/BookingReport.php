<?php

declare(strict_types=1);

namespace App\Observability;

use App\Enums\OrderStatus;
use App\Models\Order;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Métricas de negócio calculadas direto no banco (fonte da verdade), para
 * relatórios e alertas que não podem depender da amostragem do Pulse.
 */
final class BookingReport
{
    /**
     * @return array{period_hours: int, orders_created: int, orders_paid: int, orders_expired: int, orders_lost_to_conflict: int, conversion_rate: float|null, avg_seconds_to_pay: float|null, p95_seconds_to_pay: float|null}
     */
    public function since(CarbonImmutable $from): array
    {
        $created = Order::query()->where('created_at', '>=', $from);

        $byStatus = (clone $created)->toBase()->select('status', DB::raw('count(*) as aggregate'))->groupBy('status')->pluck('aggregate', 'status');
        $total = (int) $byStatus->sum();
        $paid = (int) ($byStatus[OrderStatus::Paid->value] ?? 0) + (int) ($byStatus[OrderStatus::Refunded->value] ?? 0);

        $timing = (clone $created)
            ->whereNotNull('paid_at')
            ->selectRaw('avg(extract(epoch from paid_at - created_at)) as avg_seconds')
            ->selectRaw('percentile_cont(0.95) within group (order by extract(epoch from paid_at - created_at)) as p95_seconds')
            ->first();

        return [
            'period_hours' => (int) round($from->diffInHours(now())),
            'orders_created' => $total,
            'orders_paid' => $paid,
            'orders_expired' => (int) ($byStatus[OrderStatus::Expired->value] ?? 0),
            'orders_lost_to_conflict' => (clone $created)->where('cancellation_reason', 'seat_unavailable')->count(),
            'conversion_rate' => $total > 0 ? round($paid / $total, 4) : null,
            'avg_seconds_to_pay' => self::float($timing?->getAttribute('avg_seconds')),
            'p95_seconds_to_pay' => self::float($timing?->getAttribute('p95_seconds')),
        ];
    }

    private static function float(mixed $value): ?float
    {
        return is_numeric($value) ? round((float) $value, 1) : null;
    }
}
