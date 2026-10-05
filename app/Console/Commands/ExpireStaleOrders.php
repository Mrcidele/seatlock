<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Booking\Actions\ExpireOrder;
use App\Booking\BookingSettings;
use App\Enums\OrderStatus;
use App\Models\Order;
use Illuminate\Console\Command;

/**
 * Reconciliação: expira pedidos pendentes vencidos cujo job de expiração
 * se perdeu (worker reiniciado, fila limpa, deploy...). Roda a cada minuto.
 */
class ExpireStaleOrders extends Command
{
    protected $signature = 'orders:expire-stale {--limit=500}';

    protected $description = 'Expira pedidos pendentes vencidos e libera seus assentos.';

    public function handle(ExpireOrder $expireOrder): int
    {
        $expired = 0;
        $ids = Order::query()
            ->where('status', OrderStatus::Pending)
            ->where('expires_at', '<', now()->subSeconds(BookingSettings::reconciliationGrace()))
            ->orderBy('expires_at')
            ->limit((int) $this->option('limit'))
            ->pluck('id');

        foreach ($ids as $id) {
            if ($expireOrder->handle((string) $id)) {
                $expired++;
            }
        }

        $this->info("{$expired} pedido(s) expirado(s) pela reconciliação.");

        return self::SUCCESS;
    }
}
