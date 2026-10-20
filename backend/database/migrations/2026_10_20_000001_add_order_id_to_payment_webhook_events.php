<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('payment_webhook_events', function (Blueprint $table) {
            $table->foreignUuid('order_id')->nullable()->after('id')->constrained('orders')->nullOnDelete();
            $table->index(['order_id', 'processed_at']);
        });
    }

    public function down(): void
    {
        Schema::table('payment_webhook_events', function (Blueprint $table) {
            $table->dropForeign(['order_id']);
            $table->dropIndex(['order_id', 'processed_at']);
            $table->dropColumn('order_id');
        });
    }
};
