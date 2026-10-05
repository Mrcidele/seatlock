<?php

declare(strict_types=1);

namespace App\Booking\Jobs;

use App\Booking\Actions\ExpireOrder;
use App\Models\Order;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Agendado com delay igual ao prazo do pedido. Se o pedido foi renovado,
 * um novo job é agendado e este vira no-op.
 */
final class ExpireOrderJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(public readonly string $orderId)
    {
        $this->onQueue('orders');
        $this->afterCommit();
    }

    public static function scheduleFor(Order $order): void
    {
        self::dispatch($order->id)->delay($order->expires_at);
    }

    public function handle(ExpireOrder $expireOrder): void
    {
        $expireOrder->handle($this->orderId);
    }
}
