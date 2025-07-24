<?php
// database/migrations/ngpam_000011_create_bundle_payments_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bundle_payments', function (Blueprint $table) {
            $table->id('bundle_payment_id');
            $table->string('bundle_reference')->unique(); // Reference for the bundle
            $table->foreignId('customer_id')->constrained('customers', 'customer_id')->onDelete('cascade');
            $table->decimal('total_amount', 12, 2); // Total amount for all bills in bundle
            $table->integer('bill_count'); // Number of bills in the bundle
            $table->enum('status', ['pending', 'paid', 'failed', 'expired'])->default('pending');
            $table->enum('payment_method', ['cash', 'transfer', 'qris', 'other'])->default('qris');
            $table->string('payment_reference')->nullable(); // External payment reference (Tripay, etc.)
            $table->text('tripay_data')->nullable(); // JSON data from Tripay
            $table->foreignId('collector_id')->nullable()->constrained('users', 'id')->onDelete('set null');
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['customer_id', 'status']);
            $table->index(['status', 'created_at']);
            $table->index('bundle_reference');
            $table->index('payment_reference');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bundle_payments');
    }
};