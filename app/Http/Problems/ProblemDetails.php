<?php

declare(strict_types=1);

namespace App\Http\Problems;

use Illuminate\Http\JsonResponse;

/**
 * Resposta de erro padronizada (RFC 9457 - Problem Details for HTTP APIs).
 */
final readonly class ProblemDetails
{
    /**
     * @param  array<string, mixed>  $extensions
     * @param  array<string, string>  $headers
     */
    public function __construct(
        public int $status,
        public string $title,
        public ?string $detail = null,
        public ?string $type = null,
        public ?string $instance = null,
        public array $extensions = [],
        public array $headers = [],
    ) {}

    public static function typeUri(string $slug): string
    {
        $base = config('app.url');

        return rtrim(is_string($base) ? $base : '', '/').'/problems/'.$slug;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return array_filter([
            'type' => $this->type ?? 'about:blank',
            'title' => $this->title,
            'status' => $this->status,
            'detail' => $this->detail,
            'instance' => $this->instance,
            ...$this->extensions,
        ], fn (mixed $value): bool => $value !== null);
    }

    public function toResponse(?string $instance = null): JsonResponse
    {
        $payload = $this->toArray();
        $payload['instance'] ??= $instance;

        return new JsonResponse(
            $payload,
            $this->status,
            ['Content-Type' => 'application/problem+json', ...$this->headers],
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
        );
    }
}
