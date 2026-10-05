<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Trip;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Prepara o teste de carga do k6: cria N compradores com token Sanctum e
 * escolhe a viagem e o assento disputados. Grava tudo num JSON lido pelo k6.
 */
class PrepareLoadTest extends Command
{
    protected $signature = 'seatlock:loadtest-prepare
        {--users=500 : Quantidade de compradores}
        {--output=loadtest/k6/fixtures.json : Arquivo de saída}';

    protected $description = 'Cria usuários e tokens para o teste de carga do k6.';

    public function handle(): int
    {
        if (app()->isProduction()) {
            $this->error('Não rode em produção.');

            return self::FAILURE;
        }

        $trip = Trip::factory()->create();
        $seat = $trip->vehicle->seats()->orderBy('number')->firstOrFail();
        $password = Hash::make(Str::random(32));
        $count = (int) $this->option('users');
        $tokens = [];

        $this->withProgressBar(range(1, $count), function () use (&$tokens, $password): void {
            $user = User::query()->create([
                'name' => 'Carga '.Str::random(6),
                'email' => 'k6-'.Str::lower((string) Str::ulid()).'@seatlock.test',
                'password' => $password,
            ]);

            $tokens[] = $user->createToken('k6')->plainTextToken;
        });

        $output = base_path(is_string($this->option('output')) ? $this->option('output') : 'loadtest/k6/fixtures.json');
        file_put_contents($output, json_encode([
            'trip_id' => $trip->id,
            'seat_id' => $seat->id,
            'origin' => 0,
            'destination' => $trip->stopCount() - 1,
            'tokens' => $tokens,
        ], JSON_PRETTY_PRINT));

        $this->newLine();
        $this->info("Viagem {$trip->id}, assento {$seat->number} ({$seat->id}); {$count} tokens em {$output}");

        return self::SUCCESS;
    }
}
