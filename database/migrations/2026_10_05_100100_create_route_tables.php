<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('routes', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->string('code', 20)->unique();
            $table->string('name');
            $table->timestamps();
        });

        Schema::create('stops', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('route_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('sequence');
            $table->string('name');
            $table->string('city');
            $table->char('state', 2);
            $table->unsignedInteger('minutes_from_origin');
            $table->unsignedInteger('fare_from_origin_cents');
            $table->timestamps();

            $table->unique(['route_id', 'sequence']);
        });

        Schema::create('trips', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('route_id')->constrained();
            $table->foreignUlid('vehicle_id')->constrained();
            $table->timestampTz('departure_at');
            $table->string('status', 20)->default('scheduled');
            $table->timestamps();

            $table->index(['route_id', 'departure_at']);
        });

        DB::statement('ALTER TABLE stops ADD CONSTRAINT stops_fare_non_negative CHECK (fare_from_origin_cents >= 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('trips');
        Schema::dropIfExists('stops');
        Schema::dropIfExists('routes');
    }
};
