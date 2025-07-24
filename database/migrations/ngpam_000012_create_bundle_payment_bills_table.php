<?php
// database/migrations/ngpam_000012_create_bundle_payment_bills_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bundle_payment_bills', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bundle_payment_id')->constrained('bundle_payments', 'bundle_payment_id')->onDelete('cascade');
            $table->foreignId('bill_id')->constrained('bills', 'bill_id')->onDelete('cascade');
            $table->decimal('bill_amount', 10, 2); // Individual bill amount at time of bundle creation
            $table->timestamps();

            $table->unique(['bundle_payment_id', 'bill_id']);
            $table->index('bundle_payment_id');
            $table->index('bill_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bundle_payment_bills');
    }
};