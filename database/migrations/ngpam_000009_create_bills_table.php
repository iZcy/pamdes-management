<?php
// database/migrations/ngpam_000009_create_bills_table.php
// Simplified bills system - bills are just unpaid/paid, bundling handled by payments

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Create the bills table for water usage bills
        Schema::create('bills', function (Blueprint $table) {
            // Primary key
            $table->id('bill_id');
            
            // Customer and usage relationships
            $table->foreignId('customer_id')->constrained('customers', 'customer_id')->onDelete('cascade');
            $table->foreignId('usage_id')->constrained('water_usages', 'usage_id')->onDelete('cascade');
            $table->foreignId('tariff_id')->nullable()->constrained('water_tariffs', 'tariff_id')->onDelete('set null');
            
            // Bill amounts
            $table->decimal('water_charge', 10, 2)->default(0);
            $table->decimal('admin_fee', 10, 2)->default(0);
            $table->decimal('maintenance_fee', 10, 2)->default(0);
            $table->decimal('total_amount', 10, 2);
            
            // Simple status - pure bill status without payment references
            $table->enum('status', ['unpaid', 'paid'])->default('unpaid');
            
            // Due dates and payment dates
            $table->date('due_date')->nullable();
            $table->date('payment_date')->nullable();
            
            // Additional information
            $table->text('notes')->nullable();
            
            $table->timestamps();

            // Indexes for performance
            $table->index(['customer_id', 'status']);
            $table->index(['status', 'due_date']);
            $table->index('payment_date');
            $table->index('usage_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bills');
    }
};