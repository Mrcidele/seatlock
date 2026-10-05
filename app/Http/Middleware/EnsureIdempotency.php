<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Http\Problems\ProblemDetails;
use App\Models\IdempotencyKey;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Garante que um POST repetido com o mesmo Idempotency-Key (clique duplo,
 * retry do cliente após timeout) produza um único efeito.
 *
 * - Primeira requisição: grava a chave (UNIQUE(scope, key)) e processa.
 * - Repetição concluída: devolve a mesma resposta com Idempotent-Replayed: true.
 * - Repetição enquanto a primeira ainda processa: 409.
 * - Mesma chave com outro corpo/rota: 422.
 * - Erro 5xx ou exceção: a chave é apagada para permitir novo retry.
 */
final class EnsureIdempotency
{
    public const string HEADER = 'Idempotency-Key';

    public function handle(Request $request, Closure $next): Response
    {
        $key = $request->header(self::HEADER);

        if (! is_string($key) || preg_match('/^[\x21-\x7E]{8,255}$/', $key) !== 1) {
            return $this->problem(400, 'Idempotency-Key obrigatório', 'Envie o header Idempotency-Key (8 a 255 caracteres ASCII visíveis) para esta operação.', 'idempotency-key-required');
        }

        $scope = $this->scope($request);
        $hash = hash('sha256', $request->method().' '.$request->path().' '.$request->getContent());
        $now = now();

        $inserted = IdempotencyKey::query()->insertOrIgnore([
            'id' => (string) Str::ulid(),
            'scope' => $scope,
            'key' => $key,
            'method' => $request->method(),
            'path' => $request->path(),
            'request_hash' => $hash,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        if ($inserted === 0) {
            return $this->replay($scope, $key, $hash);
        }

        try {
            $response = $next($request);
        } catch (Throwable $e) {
            $this->forget($scope, $key);

            throw $e;
        }

        if (! $response instanceof Response) {
            $this->forget($scope, $key);

            return $this->problem(500, 'Erro interno', 'Resposta inesperada.', 'internal-error');
        }

        if ($response->getStatusCode() >= 500) {
            $this->forget($scope, $key);

            return $response;
        }

        IdempotencyKey::query()->where('scope', $scope)->where('key', $key)->update([
            'response_status' => $response->getStatusCode(),
            'response_headers' => json_encode(['Content-Type' => (string) $response->headers->get('Content-Type', 'application/json')]),
            'response_body' => (string) $response->getContent(),
            'completed_at' => now(),
            'updated_at' => now(),
        ]);

        return $response;
    }

    private function replay(string $scope, string $key, string $hash): Response
    {
        $stored = IdempotencyKey::query()->where('scope', $scope)->where('key', $key)->first();

        if ($stored === null) {
            return $this->problem(409, 'Requisição em processamento', 'Tente novamente em instantes.', 'idempotency-in-progress');
        }

        if (! hash_equals($stored->request_hash, $hash)) {
            return $this->problem(422, 'Idempotency-Key reutilizado', 'Esta chave já foi usada com outra requisição. Gere uma nova chave para uma nova operação.', 'idempotency-key-reused');
        }

        if ($stored->completed_at === null || $stored->response_status === null) {
            return $this->problem(409, 'Requisição em processamento', 'Uma requisição com esta chave ainda está sendo processada.', 'idempotency-in-progress');
        }

        return new Response(
            $stored->response_body ?? '',
            $stored->response_status,
            [...($stored->response_headers ?? []), 'Idempotent-Replayed' => 'true'],
        );
    }

    private function forget(string $scope, string $key): void
    {
        IdempotencyKey::query()->where('scope', $scope)->where('key', $key)->delete();
    }

    private function scope(Request $request): string
    {
        $id = $request->user()?->getAuthIdentifier();

        return is_scalar($id) ? 'user:'.$id : 'ip:'.$request->ip();
    }

    private function problem(int $status, string $title, string $detail, string $slug): Response
    {
        return (new ProblemDetails($status, $title, $detail, ProblemDetails::typeUri($slug)))->toResponse();
    }
}
