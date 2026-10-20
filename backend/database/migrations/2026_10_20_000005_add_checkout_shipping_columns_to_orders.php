<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Additive compatibility migration for databases created before checkout
     * shipping fields were introduced. Each column is guarded so this remains
     * safe against partially-applied historical migrations.
     */
    public function up(): void
    {
        $columns = [
            'shipping_zip_code' => fn (Blueprint $table) => $table->string('shipping_zip_code', 9)->nullable(),
            'shipping_address' => fn (Blueprint $table) => $table->string('shipping_address')->nullable(),
            'shipping_city' => fn (Blueprint $table) => $table->string('shipping_city', 100)->nullable(),
            'shipping_state' => fn (Blueprint $table) => $table->string('shipping_state', 2)->nullable(),
            'shipping_service' => fn (Blueprint $table) => $table->string('shipping_service', 50)->nullable(),
            'shipping_cost_cents' => fn (Blueprint $table) => $table->bigInteger('shipping_cost_cents')->default(0),
        ];

        foreach ($columns as $name => $definition) {
            if (! Schema::hasColumn('orders', $name)) {
                Schema::table('orders', $definition);
            }
        }
    }

    public function down(): void
    {
        // Intentionally non-destructive: these columns may contain checkout data.
    }
};
