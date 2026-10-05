<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Observability\BookingReport;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MetricsController extends Controller
{
    /**
     * Métricas de negócio do período (somente admin): conversão, pedidos
     * expirados, perdas por conflito e tempo até pagar.
     */
    public function __invoke(Request $request, BookingReport $report): JsonResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User && $user->role === UserRole::Admin, 403);

        $request->validate(['hours' => ['nullable', 'integer', 'min:1', 'max:720']]);
        $hours = $request->integer('hours', 24);

        return new JsonResponse(['data' => $report->since(now()->subHours($hours))]);
    }
}
