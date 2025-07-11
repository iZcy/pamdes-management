<?php
// database/migrations/xxxx_xx_xx_create_variables_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('variables', function (Blueprint $table) {
            $table->id();
            $table->string('village_id')->nullable(); // null for global settings
            $table->foreign('village_id')->references('id')->on('villages')->onDelete('cascade');

            // Tripay configuration fields
            $table->boolean('tripay_use_main')->default(false);
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

            // Other settings (JSON)
            $table->json('other_settings')->nullable();

            $table->timestamps();

            // Indexes
            $table->index('village_id');
            $table->unique(['village_id']); // One setting per village
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('variables');
    }
};
