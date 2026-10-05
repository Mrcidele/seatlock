<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Layout\GenerateSeatsFromLayout;
use App\Layout\LayoutPresets;
use App\Models\Vehicle;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Vehicle>
 */
class VehicleFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => 'Ônibus '.fake()->unique()->numberBetween(100, 99999),
            'plate' => strtoupper(fake()->unique()->bothify('???#?##')),
            'layout' => LayoutPresets::compact(),
        ];
    }

    /**
     * @param  array<string, mixed>  $layout
     */
    public function withLayout(array $layout): static
    {
        return $this->state(fn (): array => ['layout' => $layout]);
    }

    public function configure(): static
    {
        return $this->afterCreating(function (Vehicle $vehicle): void {
            app(GenerateSeatsFromLayout::class)->handle($vehicle);
        });
    }
}
