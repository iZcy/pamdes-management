<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('variables', function (Blueprint $table) {
            // Tripay configuration fields
            $table->boolean('tripay_use_main')->default(true);
            $table->boolean('tripay_is_production')->default(false);

            // Production credentials (encrypted)
            $table->text('tripay_api_key_prod')->nullable();
            $table->text('tripay_private_key_prod')->nullable();
            $table->text('tripay_merchant_code_prod')->nullable();

            // Development/Sandbox credentials (encrypted)
            $table->text('tripay_api_key_dev')->nullable();
            $table->text('tripay_private_key_dev')->nullable();
            $table->text('tripay_merchant_code_dev')->nullable();

            // Additional Tripay settings
            $table->integer('tripay_timeout_minutes')->default(15);
            $table->string('tripay_callback_url')->nullable();
            $table->string('tripay_return_url')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('variables');
    }
};
