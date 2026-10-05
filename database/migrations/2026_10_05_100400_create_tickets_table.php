<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tickets', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('reservation_id')->unique()->constrained()->cascadeOnDelete();
            $table->timestampTz('issued_at');
            $table->timestampTz('used_at')->nullable();
            $table->foreignUlid('used_by')->nullable()->constrained('users');
            $table->timestampTz('revoked_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tickets');
    }
};
