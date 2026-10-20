<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            if (! Schema::hasColumn('orders', 'gateway_payment_id')) {
                $table->string('gateway_payment_id')->nullable()->index();
            }
            if (! Schema::hasColumn('orders', 'paid_at')) {
                $table->timestamp('paid_at')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            if (Schema::hasColumn('orders', 'gateway_payment_id')) $table->dropColumn('gateway_payment_id');
            if (Schema::hasColumn('orders', 'paid_at')) $table->dropColumn('paid_at');
        });
    }
};
