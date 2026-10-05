<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('order_id')->constrained();
            $table->string('gateway', 30);
            $table->string('method', 10);
            $table->string('status', 20);
            $table->unsignedBigInteger('amount_cents');
            $table->unsignedBigInteger('refunded_cents')->default(0);
            $table->char('currency', 3);
            $table->string('external_id')->nullable();
            $table->text('pix_copy_paste')->nullable();
            $table->timestampTz('pix_expires_at')->nullable();
            $table->string('card_brand', 20)->nullable();
            $table->string('card_last_four', 4)->nullable();
            $table->string('failure_reason')->nullable();
            $table->timestampTz('approved_at')->nullable();
            $table->timestampTz('refunded_at')->nullable();
            $table->timestamps();

            $table->unique(['gateway', 'external_id']);
            $table->index(['order_id', 'status']);
        });

        Schema::create('webhook_events', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->string('provider', 30);
            $table->string('event_id');
            $table->string('type');
            $table->jsonb('payload');
            $table->string('status', 20);
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->text('error')->nullable();
            $table->timestampTz('processed_at')->nullable();
            $table->timestamps();

            // Processamento idempotente: o mesmo evento nunca é gravado duas vezes.
            $table->unique(['provider', 'event_id']);
        });

        Schema::create('idempotency_keys', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->string('scope');
            $table->string('key');
            $table->string('method', 10);
            $table->string('path');
            $table->char('request_hash', 64);
            $table->unsignedSmallInteger('response_status')->nullable();
            $table->jsonb('response_headers')->nullable();
            $table->text('response_body')->nullable();
            $table->timestampTz('completed_at')->nullable();
            $table->timestamps();

            $table->unique(['scope', 'key']);
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('idempotency_keys');
        Schema::dropIfExists('webhook_events');
        Schema::dropIfExists('payments');
    }
};
