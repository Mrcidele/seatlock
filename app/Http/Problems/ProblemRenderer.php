<?php

declare(strict_types=1);

namespace App\Http\Problems;

use App\Exceptions\InvalidOrderTransition;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

/**
 * Converte qualquer exceção lançada numa rota da API em application/problem+json.
 */
final class ProblemRenderer
{
    public function __invoke(Throwable $e, Request $request): ?JsonResponse
    {
        if (! $request->is('api/*')) {
            return null;
        }

        return $this->toProblem($e)->toResponse($request->getRequestUri());
    }

    public function toProblem(Throwable $e): ProblemDetails
    {
        return match (true) {
            $e instanceof ApiProblem => $e->toProblem(),
            $e instanceof ValidationException => new ProblemDetails(
                status: 422,
                title: 'Dados inválidos',
                detail: $e->getMessage(),
                type: ProblemDetails::typeUri('validation-error'),
                extensions: ['errors' => $e->errors()],
            ),
            $e instanceof AuthenticationException => new ProblemDetails(401, 'Não autenticado', 'Envie um token Bearer válido.'),
            $e instanceof AuthorizationException => new ProblemDetails(403, 'Acesso negado', $e->getMessage()),
            $e instanceof ModelNotFoundException => new ProblemDetails(404, 'Recurso não encontrado'),
            $e instanceof InvalidOrderTransition => new ProblemDetails(
                status: 409,
                title: 'Transição de pedido inválida',
                detail: $e->getMessage(),
                type: ProblemDetails::typeUri('invalid-order-transition'),
            ),
            $e instanceof ThrottleRequestsException => new ProblemDetails(
                status: 429,
                title: 'Muitas requisições',
                detail: 'Aguarde antes de tentar novamente.',
                type: ProblemDetails::typeUri('rate-limited'),
                headers: $this->stringHeaders($e->getHeaders()),
            ),
            $e instanceof HttpExceptionInterface => new ProblemDetails(
                status: $e->getStatusCode(),
                title: Response::$statusTexts[$e->getStatusCode()] ?? 'Erro',
                detail: $e->getMessage() !== '' ? $e->getMessage() : null,
                headers: $this->stringHeaders($e->getHeaders()),
            ),
            default => new ProblemDetails(
                status: 500,
                title: 'Erro interno',
                detail: config('app.debug') === true ? $e->getMessage() : null,
            ),
        };
    }

    /**
     * @param  array<array-key, mixed>  $headers
     * @return array<string, string>
     */
    private function stringHeaders(array $headers): array
    {
        $result = [];
        foreach ($headers as $name => $value) {
            if (is_scalar($value)) {
                $result[(string) $name] = (string) $value;
            }
        }

        return $result;
    }
}
