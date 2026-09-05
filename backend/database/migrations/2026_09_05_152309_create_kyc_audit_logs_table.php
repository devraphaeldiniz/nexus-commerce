<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kyc_audit_logs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('seller_profile_id')->constrained('seller_profiles')->cascadeOnDelete();
            $table->string('previous_status');
            $table->string('new_status');
            $table->string('reason');
            $table->string('evaluated_by')->default('SYSTEM_COMPLIANCE');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kyc_audit_logs');
    }
};
