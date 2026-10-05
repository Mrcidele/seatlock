<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\Payment;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Auditoria pós-teste de carga (também útil em produção): procura qualquer
 * segmento de assento vendido mais de uma vez e pagamentos sem assento.
 */
class VerifyNoDoubleBooking extends Command
{
    protected $signature = 'seatlock:verify {--trip= : Restringe a uma viagem}';

    protected $description = 'Verifica que nenhum assento foi vendido duas vezes no mesmo segmento.';

    public function handle(): int
    {
        $trip = $this->option('trip');

        $duplicates = DB::table('seat_segments')
            ->when(is_string($trip), fn ($query) => $query->where('trip_id', $trip))
            ->select('trip_id', 'seat_id', 'segment_index', DB::raw('count(*) as sales'))
            ->groupBy('trip_id', 'seat_id', 'segment_index')
            ->havingRaw('count(*) > 1')
            ->get();

        $paidOrders = Order::query()->when(is_string($trip), fn ($q) => $q->where('trip_id', $trip))->where('status', OrderStatus::Paid)->count();
        $approvedWithoutSeat = Payment::query()
            ->where('status', PaymentStatus::Approved)
            ->whereHas('order', fn ($q) => $q->where('status', '!=', OrderStatus::Paid)->when(is_string($trip), fn ($q) => $q->where('trip_id', $trip)))
            ->count();

        $this->table(['métrica', 'valor'], [
            ['segmentos vendidos em duplicidade', $duplicates->count()],
            ['pedidos pagos', $paidOrders],
            ['pagamentos aprovados sem assento (aguardando estorno)', $approvedWithoutSeat],
            ['pagamentos estornados', Payment::query()->where('status', PaymentStatus::Refunded)->count()],
        ]);

        if ($duplicates->isNotEmpty()) {
            $this->error('VENDA DUPLICADA DETECTADA.');

            return self::FAILURE;
        }

        $this->info('Nenhuma venda duplicada.');

        return self::SUCCESS;
    }
}
