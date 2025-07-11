<?php
// database/migrations/2024_01_01_000006_create_payments_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id('payment_id');
            $table->foreignId('bill_id')->constrained('bills', 'bill_id')->onDelete('cascade');
            $table->date('payment_date');
            $table->decimal('amount_paid', 10, 2);
            $table->decimal('change_given', 10, 2)->default(0);
            $table->enum('payment_method', ['cash', 'transfer', 'qris', 'other'])->default('cash');
            $table->string('payment_reference')->nullable();
            $table->foreignId('collector_id')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index('payment_date');
            $table->index('payment_method');

            $table->foreign('collector_id')->references('id')->on('users')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
