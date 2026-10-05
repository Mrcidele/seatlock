<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Correlaciona logs, jobs e erros de uma mesma requisição. O ID entra no
 * Context do Laravel, que é anexado a todo log estruturado e propagado
 * para os jobs disparados durante a requisição.
 */
final class AssignRequestId
{
    public const string HEADER = 'X-Request-Id';

    public function handle(Request $request, Closure $next): Response
    {
        $incoming = $request->header(self::HEADER);
        $id = is_string($incoming) && preg_match('/^[A-Za-z0-9-]{8,64}$/', $incoming) === 1 ? $incoming : (string) Str::uuid();

        Context::add('request_id', $id);

        $userId = $request->user()?->getAuthIdentifier();
        if (is_scalar($userId)) {
            Context::add('user_id', (string) $userId);
        }

        $response = $next($request);
        assert($response instanceof Response);
        $response->headers->set(self::HEADER, $id);

        return $response;
    }
}
