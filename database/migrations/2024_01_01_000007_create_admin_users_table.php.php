<?php
// database/migrations/2024_01_01_000007_create_admin_users_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('admin_users', function (Blueprint $table) {
            $table->id('user_id');
            $table->string('username')->unique();
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->string('name');
            $table->enum('role', ['admin', 'cashier', 'reader', 'village_admin'])->default('cashier');
            $table->string('contact_info')->nullable();
            $table->string('village_id')->nullable(); // Link to village system
            $table->boolean('is_active')->default(true);
            $table->rememberToken();
            $table->timestamps();

            $table->index(['village_id', 'role', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_users');
    }
};
