<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Ampliar a tabela users com campos de 2FA e verificação de e-mail por código OTP
        Schema::table('users', function (Blueprint $table) {
            $table->string('email_verification_code', 6)->nullable()->after('email_verified_at');
            $table->timestamp('email_verification_expires_at')->nullable()->after('email_verification_code');
            $table->boolean('two_factor_enabled')->default(false)->after('role');
            $table->string('two_factor_secret')->nullable()->after('two_factor_enabled');
            $table->json('two_factor_recovery_codes')->nullable()->after('two_factor_secret');
        });

        // 2. Tabela de Cartões Salvos do Usuário (PCI Compliant: apenas brand, last 4 digits e token)
        Schema::create('user_saved_cards', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('card_holder_name');
            $table->string('card_brand', 20); // VISA, MASTERCARD, ELO, AMEX
            $table->string('last_four', 4);
            $table->string('exp_month', 2);
            $table->string('exp_year', 4);
            $table->string('gateway_card_token')->unique();
            $table->boolean('is_default')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_saved_cards');
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'email_verification_code',
                'email_verification_expires_at',
                'two_factor_enabled',
                'two_factor_secret',
                'two_factor_recovery_codes',
            ]);
        });
    }
};
