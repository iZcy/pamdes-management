<?php
// database/migrations/ngpam_000009_create_unified_bills_system.php
// Unified bills system supporting both single bills and bundle payments

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Create the unified bills table that handles both single bills and bundle payments
        Schema::create('bills', function (Blueprint $table) {
            // Primary key
            $table->id('bill_id');
            
            // Bill identification
            $table->string('bill_ref')->nullable(); // Individual bill reference (for water usage bills)
            $table->string('bundle_reference')->unique(); // Bundle reference (every bill has one, single bills too)
            
            // Customer relationship (direct for bundle bills, indirect through usage for single bills)
            $table->foreignId('customer_id')->constrained('customers', 'customer_id')->onDelete('cascade');
            
            // Water usage relationship (null for bundle bills, required for single bills)
            $table->foreignId('usage_id')->nullable()->constrained('water_usages', 'usage_id')->onDelete('cascade');
            
            // Tariff relationship (null for bundle bills)
            $table->foreignId('tariff_id')->nullable()->constrained('water_tariffs', 'tariff_id')->onDelete('set null');
            
            // Bill amounts (for single bills from water usage)
            $table->decimal('water_charge', 10, 2)->default(0);
            $table->decimal('admin_fee', 10, 2)->default(0);
            $table->decimal('maintenance_fee', 10, 2)->default(0);
            
            // Total amount (calculated for single bills, sum for bundle bills)
            $table->decimal('total_amount', 10, 2);
            
            // Bundle information
            $table->integer('bill_count')->default(1); // 1 for single bills, >1 for bundle bills
            
            // Status and payment information
            $table->enum('status', ['paid', 'pending', 'unpaid', 'overdue', 'failed', 'expired'])->default('unpaid');
            $table->enum('payment_method', ['cash', 'transfer', 'qris', 'other'])->default('cash');
            $table->string('payment_reference')->nullable(); // External payment reference (Tripay, etc.)
            $table->json('tripay_data')->nullable(); // JSON data from Tripay payment gateway
            
            // Payment tracking
            $table->foreignId('collector_id')->nullable()->constrained('users', 'id')->onDelete('set null');
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('expires_at')->nullable(); // For bundle payments with expiration
            
            // Due dates and payment dates
            $table->date('due_date')->nullable();
            $table->date('payment_date')->nullable();
            
            // Additional information
            $table->text('notes')->nullable();
            
            $table->timestamps();

            // Indexes for performance
            $table->index('bill_ref');
            $table->index('bundle_reference');
            $table->index(['customer_id', 'status']);
            $table->index(['status', 'due_date']);
            $table->index('payment_date');
            $table->index('payment_reference');
            $table->index('collector_id');
            $table->index('bill_count'); // To differentiate single vs bundle bills
        });

        // Create bill_bundles table for managing which bills are bundled together
        Schema::create('bill_bundles', function (Blueprint $table) {
            $table->id();
            
            // The main bundle bill (bill_count > 1)
            $table->foreignId('bundle_bill_id')->constrained('bills', 'bill_id')->onDelete('cascade');
            
            // The individual bills that are part of this bundle (bill_count = 1)
            $table->foreignId('child_bill_id')->constrained('bills', 'bill_id')->onDelete('cascade');
            
            // Original amount of the child bill when it was bundled
            $table->decimal('original_amount', 10, 2);
            
            $table->timestamps();

            // Constraints and indexes
            $table->unique(['bundle_bill_id', 'child_bill_id']);
            $table->index('bundle_bill_id');
            $table->index('child_bill_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bill_bundles');
        Schema::dropIfExists('bills');
    }
};