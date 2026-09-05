<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Registro da Reclamação / Disputa
        Schema::create('order_disputes', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('order_id')->constrained('orders')->cascadeOnDelete();
            $table->foreignUuid('seller_id')->constrained('seller_profiles')->cascadeOnDelete();
            $table->string('customer_email');
            $table->enum('reason', [
                'PRODUCT_NOT_RECEIVED',
                'PRODUCT_DEFECTIVE',
                'PRODUCT_DIFFERENT',
                'REGRET_OF_PURCHASE'
            ]);
            $table->text('description');
            $table->enum('status', [
                'OPEN',
                'UNDER_SELLER_REVIEW',
                'ESCALATED_TO_MEDIATION',
                'RESOLVED_REFUNDED',
                'RESOLVED_FAVOR_SELLER',
                'CLOSED'
            ])->default('OPEN');
            $table->bigInteger('disputed_amount_cents');
            $table->text('resolution_notes')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();
        });

        // Histórico de mensagens/evidências trocadas no SAC
        Schema::create('dispute_messages', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('dispute_id')->constrained('order_disputes')->cascadeOnDelete();
            $table->enum('sender_type', ['CUSTOMER', 'SELLER', 'MEDIATOR']);
            $table->string('sender_name');
            $table->text('message');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dispute_messages');
        Schema::dropIfExists('order_disputes');
    }
};
