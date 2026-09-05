<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Registro de cada item com split de comissão e vínculo ao seller
        Schema::table('order_items', function (Blueprint $table) {
            $table->foreignUuid('seller_id')->nullable()->constrained('seller_profiles');
            $table->integer('commission_rate_bps')->default(1300); // 13.00% em basis points
            $table->bigInteger('fee_platform_cents')->default(0);  // Comissão da plataforma
            $table->bigInteger('net_seller_cents')->default(0);    // Líquido do vendedor
        });

        // Status mais rico do ciclo de vida do pedido
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('status');
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->enum('status', [
                'PENDING_PAYMENT',
                'PAID',
                'IN_PREPARATION',
                'SHIPPED',
                'DELIVERED',
                'CANCELED',
                'DISPUTED'
            ])->default('PAID');
            $table->timestamp('delivered_at')->nullable();
        });

        // Livro-razão (Ledger) financeiro de dupla entrada para auditoria
        Schema::create('wallet_transactions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('wallet_id')->constrained('seller_wallets')->cascadeOnDelete();
            $table->foreignUuid('order_id')->nullable()->constrained('orders')->nullOnDelete();
            $table->enum('type', ['ESCROW_HOLD', 'ESCROW_RELEASE', 'REFUND_DEBIT', 'PAYOUT']);
            $table->bigInteger('amount_cents');
            $table->string('description');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wallet_transactions');
        Schema::table('order_items', function (Blueprint $table) {
            $table->dropForeign(['seller_id']);
            $table->dropColumn(['seller_id', 'commission_rate_bps', 'fee_platform_cents', 'net_seller_cents']);
        });
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['status', 'delivered_at']);
        });
        Schema::table('orders', function (Blueprint $table) {
            $table->string('status')->default('PAID');
        });
    }
};
