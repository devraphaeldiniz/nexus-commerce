<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pending_registrations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('email')->unique();
            $table->string('name');
            $table->string('cpf', 14)->unique();
            $table->string('phone', 20);
            $table->date('birth_date')->nullable();
            $table->string('password'); // Já armazenado em hash Bcrypt
            $table->string('verification_code', 6);
            $table->timestamp('expires_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pending_registrations');
    }
};
