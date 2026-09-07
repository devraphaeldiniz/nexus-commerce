<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('order_escrows')) {
            Schema::create('order_escrows', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('order_id');
                $table->uuid('order_item_id')->nullable();
                $table->uuid('seller_id')->nullable();
                $table->bigInteger('gross_amount_cents');
                $table->bigInteger('platform_fee_cents');
                $table->bigInteger('net_seller_cents');
                $table->string('status')->default('HELD'); // HELD, RELEASED, REFUNDED
                $table->timestamps();

                $table->foreign('order_id')->references('id')->on('orders')->onDelete('cascade');
                $table->foreign('seller_id')->references('id')->on('seller_profiles')->onDelete('cascade');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('order_escrows');
    }
};
