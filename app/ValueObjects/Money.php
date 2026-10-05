<?php

declare(strict_types=1);

namespace App\ValueObjects;

use InvalidArgumentException;
use JsonSerializable;

/**
 * Valor monetário em centavos (inteiro). Nunca usamos float para dinheiro.
 */
final readonly class Money implements JsonSerializable
{
    public function __construct(
        public int $cents,
        public string $currency = 'BRL',
    ) {
        if (preg_match('/^[A-Z]{3}$/', $currency) !== 1) {
            throw new InvalidArgumentException("Moeda inválida: {$currency}");
        }
    }

    public static function of(int $cents, string $currency = 'BRL'): self
    {
        return new self($cents, $currency);
    }

    public static function zero(string $currency = 'BRL'): self
    {
        return new self(0, $currency);
    }

    public function add(self $other): self
    {
        $this->assertSameCurrency($other);

        return new self($this->cents + $other->cents, $this->currency);
    }

    public function subtract(self $other): self
    {
        $this->assertSameCurrency($other);

        return new self($this->cents - $other->cents, $this->currency);
    }

    /**
     * Multiplica por uma fração em pontos-base (10_000 = 100%), arredondando
     * meio centavo para cima (half up) com aritmética inteira.
     */
    public function multiplyBasisPoints(int $basisPoints): self
    {
        if ($basisPoints < 0) {
            throw new InvalidArgumentException('Pontos-base não podem ser negativos.');
        }

        return new self(intdiv($this->cents * $basisPoints + 5_000, 10_000), $this->currency);
    }

    public function times(int $quantity): self
    {
        return new self($this->cents * $quantity, $this->currency);
    }

    public function isZero(): bool
    {
        return $this->cents === 0;
    }

    public function isNegative(): bool
    {
        return $this->cents < 0;
    }

    public function equals(self $other): bool
    {
        return $this->cents === $other->cents && $this->currency === $other->currency;
    }

    public function greaterThan(self $other): bool
    {
        $this->assertSameCurrency($other);

        return $this->cents > $other->cents;
    }

    public function format(): string
    {
        $abs = abs($this->cents);
        $formatted = number_format(intdiv($abs, 100), 0, ',', '.').','.str_pad((string) ($abs % 100), 2, '0', STR_PAD_LEFT);
        $symbol = $this->currency === 'BRL' ? 'R$' : $this->currency;

        return ($this->cents < 0 ? '-' : '').$symbol.' '.$formatted;
    }

    /**
     * @return array{cents: int, currency: string, formatted: string}
     */
    public function jsonSerialize(): array
    {
        return [
            'cents' => $this->cents,
            'currency' => $this->currency,
            'formatted' => $this->format(),
        ];
    }

    private function assertSameCurrency(self $other): void
    {
        if ($this->currency !== $other->currency) {
            throw new InvalidArgumentException("Moedas diferentes: {$this->currency} e {$other->currency}");
        }
    }
}
