<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Dimensões e peso para cálculo de cubagem nos produtos
        Schema::table('products', function (Blueprint $table) {
            $table->integer('weight_grams')->default(350);     // Peso real em gramas
            $table->integer('height_cm')->default(15);          // Altura
            $table->integer('width_cm')->default(20);           // Largura
            $table->integer('length_cm')->default(30);          // Comprimento
        });

        // Informações logísticas e rastreamento nos pedidos
        Schema::table('orders', function (Blueprint $table) {
            $table->string('shipping_zip_code', 9)->nullable();
            $table->string('shipping_service')->nullable();      // Ex: EXPRESS, STANDARD
            $table->bigInteger('shipping_cost_cents')->default(0);
            $table->integer('estimated_delivery_days')->nullable();
            $table->string('tracking_code', 30)->nullable()->unique();
            $table->timestamp('shipped_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn([
                'shipping_zip_code',
                'shipping_service',
                'shipping_cost_cents',
                'estimated_delivery_days',
                'tracking_code',
                'shipped_at',
            ]);
        });

        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['weight_grams', 'height_cm', 'width_cm', 'length_cm']);
        });
    }
};
