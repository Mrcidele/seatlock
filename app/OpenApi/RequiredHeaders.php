<?php

declare(strict_types=1);

namespace App\OpenApi;

use App\Booking\LockOwner;
use App\Http\Middleware\EnsureIdempotency;
use Dedoc\Scramble\Support\Generator\Operation;
use Dedoc\Scramble\Support\Generator\Parameter;
use Dedoc\Scramble\Support\Generator\Schema;
use Dedoc\Scramble\Support\Generator\Types\StringType;
use Dedoc\Scramble\Support\RouteInfo;

/**
 * Documenta os headers exigidos pelos endpoints de compra.
 */
final class RequiredHeaders
{
    private const array CART_ROUTES = ['v1.trips.locks.store', 'v1.trips.locks.destroy', 'v1.orders.store', 'v1.trips.seat-map'];

    public function __invoke(Operation $operation, RouteInfo $routeInfo): void
    {
        $route = $routeInfo->route;

        if (in_array('idempotent', $route->gatherMiddleware(), true)) {
            $operation->addParameters([self::header(
                EnsureIdempotency::HEADER,
                true,
                '9f1c2a7e-4b6d-4f3a-9c8e-1a2b3c4d5e6f',
                'Chave única por operação. Repetir a requisição com a mesma chave devolve a mesma resposta.',
            )]);
        }

        if (in_array($route->getName(), self::CART_ROUTES, true)) {
            $operation->addParameters([self::header(
                LockOwner::HEADER,
                $route->getName() !== 'v1.trips.seat-map',
                'cart-7d1e9b20',
                'Identificador do carrinho (8 a 64 caracteres). Dono dos locks temporários.',
            )]);
        }
    }

    private static function header(string $name, bool $required, string $example, string $description): Parameter
    {
        $type = new StringType;
        $type->example($example);

        $parameter = new Parameter($name, 'header');
        $parameter->required($required);
        $schema = Schema::fromType($type);
        assert($schema instanceof Schema);
        $parameter->setSchema($schema);
        $parameter->description($description);

        return $parameter;
    }
}
