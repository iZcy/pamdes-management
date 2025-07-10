<?php
// database/migrations/2024_01_01_000005_create_bills_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bills', function (Blueprint $table) {
            $table->id('bill_id');
            $table->foreignId('usage_id')->constrained('water_usages', 'usage_id')->onDelete('cascade');
            $table->foreignId('tariff_id')->nullable()->constrained('water_tariffs', 'tariff_id')->onDelete('set null');
            $table->decimal('water_charge', 10, 2)->default(0);
            $table->decimal('admin_fee', 10, 2)->default(0);
            $table->decimal('maintenance_fee', 10, 2)->default(0);
            $table->decimal('total_amount', 10, 2);
            $table->enum('status', ['paid', 'unpaid', 'overdue'])->default('unpaid');
            $table->date('due_date')->nullable();
            $table->date('payment_date')->nullable();
            $table->timestamps();

            $table->index(['status', 'due_date']);
            $table->index('payment_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bills');
    }
};
