<?php
// database/migrations/2024_01_01_000004_create_water_usages_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('water_usages', function (Blueprint $table) {
            $table->id('usage_id');
            $table->foreignId('customer_id')->constrained('customers', 'customer_id')->onDelete('cascade');
            $table->foreignId('period_id')->constrained('billing_periods', 'period_id')->onDelete('cascade');
            $table->integer('initial_meter')->default(0);
            $table->integer('final_meter');
            $table->integer('total_usage_m3')->nullable();
            $table->date('usage_date');
            $table->string('reader_name')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['customer_id', 'period_id']);
            $table->index(['period_id', 'usage_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('water_usages');
    }
};
