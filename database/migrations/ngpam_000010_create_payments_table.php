<?php
// database/migrations/ngpam_000010_create_payments_table.php
// Payments can handle multiple bills (bundle payments)

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id('payment_id');
            $table->date('payment_date');
            $table->decimal('total_amount', 10, 2); // Total amount for this payment
            $table->decimal('change_given', 10, 2)->default(0);
            $table->enum('payment_method', ['cash', 'transfer', 'qris', 'other'])->default('cash');
            $table->enum('status', ['pending', 'completed', 'expired'])->default('pending');
            $table->string('transaction_ref')->nullable(); // Tripay transaction reference
            $table->json('tripay_data')->nullable(); // Tripay response data
            $table->foreignId('collector_id')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index('payment_date');
            $table->index('payment_method');
            $table->index('status');
            $table->index('transaction_ref');

            $table->foreign('collector_id')->references('id')->on('users')->onDelete('set null');
        });

        // Pivot table for payment-bill relationships (one payment can pay multiple bills)
        Schema::create('bill_payment', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payment_id')->constrained('payments', 'payment_id')->onDelete('cascade');
            $table->foreignId('bill_id')->constrained('bills', 'bill_id')->onDelete('cascade');
            $table->decimal('amount_paid', 10, 2); // Amount paid for this specific bill
            $table->timestamps();

            $table->unique(['payment_id', 'bill_id']);
            $table->index('payment_id');
            $table->index('bill_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bill_payment');
        Schema::dropIfExists('payments');
    }
};
