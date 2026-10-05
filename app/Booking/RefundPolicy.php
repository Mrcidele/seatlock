<?php

declare(strict_types=1);

namespace App\Booking;

use App\ValueObjects\Money;
use Carbon\CarbonImmutable;

/**
 * Reembolso por antecedência em relação ao embarque, configurado em
 * seatlock.refund_policy (faixas da maior para a menor antecedência).
 */
final readonly class RefundPolicy
{
    /**
     * @return Money|null null quando já não é possível cancelar
     */
    public function refundFor(Money $paid, CarbonImmutable $departure, ?CarbonImmutable $now = null): ?Money
    {
        $hoursBefore = ($now ?? CarbonImmutable::now())->diffInMinutes($departure, false) / 60;

        foreach ($this->tiers() as $tier) {
            if ($hoursBefore >= $tier['min_hours_before']) {
                return $paid->multiplyBasisPoints($tier['refund_basis_points']);
            }
        }

        return null;
    }

    /**
     * @return list<array{min_hours_before: int, refund_basis_points: int}>
     */
    private function tiers(): array
    {
        $tiers = config('seatlock.refund_policy');
        $result = [];

        foreach (is_array($tiers) ? $tiers : [] as $tier) {
            if (is_array($tier) && is_numeric($tier['min_hours_before'] ?? null) && is_numeric($tier['refund_basis_points'] ?? null)) {
                $result[] = ['min_hours_before' => (int) $tier['min_hours_before'], 'refund_basis_points' => (int) $tier['refund_basis_points']];
            }
        }

        usort($result, fn (array $a, array $b): int => $b['min_hours_before'] <=> $a['min_hours_before']);

        return $result;
    }
}
