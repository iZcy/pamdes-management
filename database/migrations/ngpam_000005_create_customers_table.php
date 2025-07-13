<?php
// database/migrations/2024_01_01_000001_create_customers_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customers', function (Blueprint $table) {
            $table->id('customer_id');
            $table->string('customer_code')->unique();
            $table->string('name');
            $table->string('phone_number')->nullable();
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->text('address')->nullable();
            $table->string('rt')->nullable();
            $table->string('rw')->nullable();
            $table->string('village_id')->nullable();
            $table->timestamps();

            $table->index(['village_id', 'status']);
            $table->index('customer_code');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customers');
    }
};
