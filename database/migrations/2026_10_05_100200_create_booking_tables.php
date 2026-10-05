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
        Schema::create('orders', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('user_id')->constrained();
            $table->foreignUlid('trip_id')->constrained();
            $table->unsignedSmallInteger('origin_index');
            $table->unsignedSmallInteger('destination_index');
            $table->string('status', 20);
            $table->unsignedBigInteger('total_cents');
            $table->unsignedBigInteger('refunded_cents')->default(0);
            $table->char('currency', 3);
            $table->string('lock_owner');
            $table->unsignedSmallInteger('lock_renewals')->default(0);
            $table->timestampTz('expires_at');
            $table->timestampTz('paid_at')->nullable();
            $table->timestampTz('expired_at')->nullable();
            $table->timestampTz('cancelled_at')->nullable();
            $table->timestampTz('refunded_at')->nullable();
            $table->string('cancellation_reason')->nullable();
            $table->timestamps();

            $table->index(['status', 'expires_at']);
            $table->index(['user_id', 'created_at']);
        });

        Schema::create('passengers', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->string('name');
            $table->string('document', 20);
            $table->string('email')->nullable();
            $table->string('phone', 20)->nullable();
            $table->timestamps();
        });

        Schema::create('reservations', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('order_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('trip_id')->constrained();
            $table->foreignUlid('seat_id')->constrained();
            $table->foreignUlid('passenger_id')->constrained();
            $table->unsignedSmallInteger('origin_index');
            $table->unsignedSmallInteger('destination_index');
            $table->unsignedBigInteger('price_cents');
            $table->char('currency', 3);
            $table->string('status', 20);
            $table->timestamps();

            $table->unique(['order_id', 'seat_id']);
            $table->index(['trip_id', 'seat_id']);
        });

        // A trava definitiva contra venda dupla: um assento só pode ser ocupado
        // por uma reserva em cada segmento da viagem.
        Schema::create('seat_segments', function (Blueprint $table): void {
            $table->foreignUlid('trip_id')->constrained();
            $table->foreignUlid('seat_id')->constrained();
            $table->unsignedSmallInteger('segment_index');
            $table->foreignUlid('reservation_id')->constrained()->cascadeOnDelete();
            $table->timestampTz('created_at')->useCurrent();

            $table->unique(['trip_id', 'seat_id', 'segment_index'], 'seat_segments_trip_seat_segment_unique');
            $table->index('reservation_id');
        });

        DB::statement('ALTER TABLE orders ADD CONSTRAINT orders_leg_valid CHECK (origin_index < destination_index)');
        DB::statement('ALTER TABLE reservations ADD CONSTRAINT reservations_leg_valid CHECK (origin_index < destination_index)');
        DB::statement(
            "ALTER TABLE orders ADD CONSTRAINT orders_status_valid CHECK (status IN ('pending','paid','expired','cancelled','refunded'))"
        );
        // Um carrinho (dono do lock) só pode ter um pedido pendente por viagem.
        DB::statement(
            "CREATE UNIQUE INDEX orders_one_pending_per_owner_trip ON orders (lock_owner, trip_id) WHERE status = 'pending'"
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('seat_segments');
        Schema::dropIfExists('reservations');
        Schema::dropIfExists('passengers');
        Schema::dropIfExists('orders');
    }
};
