<?php

declare(strict_types=1);

namespace App\Payments;

use App\Enums\PaymentMethod;
use App\Payments\Gateways\FakeGateway;
use App\Payments\Gateways\MercadoPagoGateway;
use App\Payments\Gateways\StripeGateway;
use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Http\Client\Factory as Http;
use InvalidArgumentException;

final class PaymentGatewayManager
{
    /** @var array<string, PaymentGateway> */
    private array $resolved = [];

    public function __construct(
        private readonly Http $http,
        private readonly Cache $cache,
    ) {}

    public function forMethod(PaymentMethod $method): PaymentGateway
    {
        $preferred = config("payments.methods.{$method->value}");
        $default = config('payments.default');

        return $this->gateway(is_string($preferred) && $preferred !== '' ? $preferred : (is_string($default) ? $default : 'fake'));
    }

    public function gateway(string $name): PaymentGateway
    {
        return $this->resolved[$name] ??= match ($name) {
            'fake' => new FakeGateway(
                $this->cache,
                $this->config('fake', 'webhook_secret'),
                $this->string('seatlock.pix.key'),
                $this->string('seatlock.pix.merchant_name'),
                $this->string('seatlock.pix.merchant_city'),
            ),
            'mercadopago' => new MercadoPagoGateway(
                $this->http,
                $this->config('mercadopago', 'base_url'),
                $this->config('mercadopago', 'access_token'),
                $this->config('mercadopago', 'webhook_secret'),
                $this->config('mercadopago', 'notification_url') ?: null,
            ),
            'stripe' => new StripeGateway(
                $this->http,
                $this->config('stripe', 'base_url'),
                $this->config('stripe', 'secret'),
                $this->config('stripe', 'webhook_secret'),
                (int) $this->config('stripe', 'webhook_tolerance'),
            ),
            default => throw new InvalidArgumentException("Gateway de pagamento desconhecido: {$name}"),
        };
    }

    public function has(string $name): bool
    {
        return in_array($name, ['fake', 'mercadopago', 'stripe'], true);
    }

    private function config(string $gateway, string $key): string
    {
        return $this->string("payments.gateways.{$gateway}.{$key}");
    }

    private function string(string $key): string
    {
        $value = config($key);

        return is_scalar($value) ? (string) $value : '';
    }
}
