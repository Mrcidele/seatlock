<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Booking\Actions\CreateOrder;
use App\Booking\Actions\LockSeats;
use App\Booking\Data\PassengerData;
use App\Booking\Exceptions\LockNotHeld;
use App\Booking\Exceptions\OrderNotModifiable;
use App\Booking\Exceptions\SeatsUnavailable;
use App\Booking\LockOwner;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Models\Trip;
use App\Models\User;
use App\Payments\Actions\StartPayment;
use App\SeatLock\InMemorySeatLockService;
use App\SeatLock\SeatLockService;
use Illuminate\Console\Command;

/**
 * Executa o fluxo completo de compra de um assento (lock -> pedido ->
 * pagamento com cartão no gateway fake -> confirmação) e imprime o resultado em JSON.
 *
 * Usado pelos testes de concorrência, que disparam vários processos deste
 * comando ao mesmo tempo disputando o mesmo assento.
 */
class SimulatePurchase extends Command
{
    protected $signature = 'seatlock:simulate-purchase
        {trip : ID da viagem}
        {seat : ID do assento}
        {user : ID do usuário comprador}
        {--origin=0} {--destination=}
        {--cart= : ID do carrinho (padrão: aleatório)}
        {--start-at= : Timestamp (float) para todos os processos largarem juntos}
        {--without-redis-lock : Simula o Redis fora do ar (lock isolado por processo)}';

    protected $description = 'Simula uma compra completa de um assento (usado nos testes de concorrência).';

    public function handle(): int
    {
        if ($this->option('without-redis-lock') === true) {
            $this->laravel->instance(SeatLockService::class, new InMemorySeatLockService);
        }

        $trip = Trip::query()->with('route.stops')->findOrFail($this->stringArgument('trip'));
        $user = User::query()->findOrFail($this->stringArgument('user'));
        $seatId = $this->stringArgument('seat');
        $destination = $this->option('destination');
        $leg = $trip->leg(
            (int) $this->option('origin'),
            is_numeric($destination) ? (int) $destination : $trip->stopCount() - 1,
        );
        $cart = $this->option('cart');
        $owner = LockOwner::for($user, is_string($cart) ? $cart : bin2hex(random_bytes(8)));

        $this->waitForStart();

        try {
            $this->laravel->make(LockSeats::class)->handle($trip, $leg, [$seatId], $owner);
        } catch (SeatsUnavailable) {
            return $this->report('lock_conflict');
        }

        try {
            $order = $this->laravel->make(CreateOrder::class)->handle($user, $trip, $leg, $owner, [
                new PassengerData($seatId, $user->name, '00000000000'),
            ]);
        } catch (SeatsUnavailable|LockNotHeld|OrderNotModifiable) {
            return $this->report('order_rejected');
        }

        // Cartão no gateway fake: aprovado na hora, o que dispara a confirmação
        // (e o estorno automático se o banco barrar a venda).
        $payment = $this->laravel->make(StartPayment::class)->handle($order, PaymentMethod::Card, 'tok_approved');
        $order->refresh();

        $result = match (true) {
            $order->status === OrderStatus::Paid => 'confirmed',
            $order->cancellation_reason === 'seat_unavailable' => 'seats_taken',
            default => 'payment_'.$payment->status->value,
        };

        return $this->report($result, $order->id);
    }

    private function waitForStart(): void
    {
        $startAt = $this->option('start-at');

        if (is_numeric($startAt)) {
            $wait = (float) $startAt - microtime(true);

            if ($wait > 0) {
                usleep((int) ($wait * 1_000_000));
            }
        }
    }

    private function report(string $result, ?string $orderId = null): int
    {
        $this->line((string) json_encode(['result' => $result, 'order_id' => $orderId]));

        return self::SUCCESS;
    }

    private function stringArgument(string $name): string
    {
        $value = $this->argument($name);

        return is_string($value) ? $value : '';
    }
}
