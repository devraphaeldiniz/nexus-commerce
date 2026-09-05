<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Perfis de Comprador
        Schema::create('customer_profiles', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('full_name');
            $table->string('document_number', 14)->unique()->nullable(); // CPF
            $table->string('phone', 20)->nullable();
            $table->timestamps();
        });

        // Endereços de entrega/faturamento
        Schema::create('addresses', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('customer_profile_id')->constrained('customer_profiles')->cascadeOnDelete();
            $table->string('street');
            $table->string('number', 20);
            $table->string('complement')->nullable();
            $table->string('neighborhood');
            $table->string('city');
            $table->string('state', 2);
            $table->string('zip_code', 9);
            $table->boolean('is_default')->default(false);
            $table->timestamps();
        });

        // Perfis de Vendedor (Sellers) e Governança KYC
        Schema::create('seller_profiles', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('store_name')->unique();
            $table->string('legal_name'); // Razão social ou Nome completo
            $table->enum('document_type', ['CPF', 'CNPJ'])->default('CNPJ');
            $table->string('document_number', 18)->unique();
            $table->string('state_registration')->nullable(); // Inscrição Estadual
            $table->enum('kyc_status', ['PENDING', 'UNDER_REVIEW', 'APPROVED', 'REJECTED', 'SUSPENDED'])->default('PENDING');
            $table->decimal('reputation_score', 3, 2)->default(5.00); // 1.00 a 5.00
            $table->integer('total_sales_count')->default(0);
            $table->decimal('cancellation_rate', 5, 2)->default(0.00); // % cancelamentos
            $table->timestamps();
        });

        // Carteira do Vendedor (Ledger / Custódia / Split)
        Schema::create('seller_wallets', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('seller_profile_id')->constrained('seller_profiles')->cascadeOnDelete();
            $table->bigInteger('balance_available_cents')->default(0); // Saldo liberado para saque
            $table->bigInteger('balance_escrow_cents')->default(0);    // Saldo retido até entrega
            $table->timestamps();
        });

        // Vincular produtos ao vendedor proprietário
        Schema::table('products', function (Blueprint $table) {
            $table->foreignUuid('seller_id')->nullable()->constrained('seller_profiles')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropForeign(['seller_id']);
            $table->dropColumn('seller_id');
        });

        Schema::dropIfExists('seller_wallets');
        Schema::dropIfExists('seller_profiles');
        Schema::dropIfExists('addresses');
        Schema::dropIfExists('customer_profiles');
    }
};
