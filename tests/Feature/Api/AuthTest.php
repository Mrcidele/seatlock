<?php

declare(strict_types=1);

use App\Models\User;

it('registers and returns a bearer token', function (): void {
    $token = $this->postJson('/api/v1/auth/register', [
        'name' => 'Ana', 'email' => 'Ana@Example.com', 'password' => 'secret-123',
    ])->assertCreated()->assertJsonPath('data.user.email', 'ana@example.com')->json('data.token');

    $this->withToken($token)->getJson('/api/v1/auth/me')->assertOk()->assertJsonPath('data.role', 'customer');
});

it('logs in with valid credentials only', function (): void {
    User::factory()->create(['email' => 'ana@example.com', 'password' => 'secret-123']);

    $this->postJson('/api/v1/auth/login', ['email' => 'ana@example.com', 'password' => 'wrong'])
        ->assertStatus(422)
        ->assertHeader('Content-Type', 'application/problem+json');

    $this->postJson('/api/v1/auth/login', ['email' => 'ana@example.com', 'password' => 'secret-123'])
        ->assertOk()
        ->assertJsonStructure(['data' => ['token', 'user' => ['id', 'email']]]);
});
