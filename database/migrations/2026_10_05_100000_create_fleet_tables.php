<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vehicles', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->string('name');
            $table->string('plate', 10)->unique();
            $table->jsonb('layout');
            $table->timestamps();
        });

        Schema::create('seats', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('vehicle_id')->constrained()->cascadeOnDelete();
            $table->string('number', 5);
            $table->unsignedSmallInteger('deck');
            $table->unsignedSmallInteger('row');
            $table->unsignedSmallInteger('column');
            $table->string('type', 20);
            $table->timestamps();

            $table->unique(['vehicle_id', 'number']);
            $table->unique(['vehicle_id', 'deck', 'row', 'column']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('seats');
        Schema::dropIfExists('vehicles');
    }
};
