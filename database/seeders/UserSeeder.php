<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        User::query()->updateOrCreate(['email' => 'cliente@seatlock.test'], [
            'name' => 'Cliente Demo',
            'password' => 'password',
            'role' => UserRole::Customer,
        ]);

        User::query()->updateOrCreate(['email' => 'operador@seatlock.test'], [
            'name' => 'Operador Demo',
            'password' => 'password',
            'role' => UserRole::Operator,
        ]);
    }
}
