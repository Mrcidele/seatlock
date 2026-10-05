<?php

declare(strict_types=1);

namespace App\Booking;

use App\Booking\Exceptions\MissingCartId;
use App\Models\User;
use Illuminate\Http\Request;

/**
 * Token do dono do lock: usuário autenticado + carrinho (header X-Cart-Id).
 * Prefixar com o usuário impede que alguém libere o lock de outro só
 * conhecendo o ID do carrinho.
 */
final class LockOwner
{
    public const string HEADER = 'X-Cart-Id';

    public static function for(User $user, string $cartId): string
    {
        return "user:{$user->id}:cart:{$cartId}";
    }

    public static function fromRequest(Request $request): string
    {
        $user = $request->user();
        $cartId = $request->header(self::HEADER);

        if (! $user instanceof User || ! is_string($cartId) || preg_match('/^[A-Za-z0-9-]{8,64}$/', $cartId) !== 1) {
            throw new MissingCartId;
        }

        return self::for($user, $cartId);
    }
}
