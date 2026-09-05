<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payout_requests', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('seller_id')->constrained('seller_profiles')->cascadeOnDelete();
            $table->foreignUuid('wallet_id')->constrained('seller_wallets')->cascadeOnDelete();
            $table->string('pix_key_type'); // CPF, CNPJ, EMAIL, PHONE, RANDOM
            $table->string('pix_key');
            $table->bigInteger('amount_cents');
            $table->bigInteger('fee_cents')->default(0); // Taxa de transferência se houver
            $table->enum('status', ['PENDING', 'PROCESSING', 'COMPLETED', 'FAILED'])->default('PENDING');
            $table->string('idempotency_key', 100)->unique();
            $table->string('bank_end_to_end_id')->nullable(); // ID E2E do BACEN/Pix
            $table->text('failure_reason')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payout_requests');
    }
};
