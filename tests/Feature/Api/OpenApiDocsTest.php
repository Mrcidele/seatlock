<?php

declare(strict_types=1);

it('serves the generated OpenAPI document', function (): void {
    $spec = $this->getJson('/docs/api.json')->assertOk()->json();

    expect($spec['openapi'])->toStartWith('3.')
        ->and($spec['paths'])->toHaveKeys(['/v1/trips/{trip}/seat-map', '/v1/trips/{trip}/locks', '/v1/orders', '/v1/orders/{order}/payments'])
        ->and($spec['components']['schemas'])->toHaveKey('ProblemDetails')
        ->and($spec['components']['securitySchemes'])->not->toBeEmpty()
        ->and(collect($spec['paths']['/v1/orders']['post']['parameters'])->pluck('name'))->toContain('Idempotency-Key', 'X-Cart-Id');
});

it('keeps the committed spec in sync with the code', function (): void {
    $committed = json_decode((string) file_get_contents(base_path('docs/openapi.json')), true);
    $current = $this->getJson('/docs/api.json')->json();

    expect(array_keys($committed['paths']))->toEqual(array_keys($current['paths']));
});
