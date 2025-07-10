<?php
// database/migrations/xxxx_xx_xx_create_villages_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('villages', function (Blueprint $table) {
            // Match main system structure
            $table->uuid('id')->primary(); // Changed from village_id to id (UUID)
            $table->string('name');
            $table->string('slug')->unique()->index(); // For subdomain routing
            $table->text('description')->nullable();
            $table->string('domain')->nullable(); // Custom domain if needed
            $table->decimal('latitude', 10, 8)->nullable();
            $table->decimal('longitude', 11, 8)->nullable();
            $table->string('phone_number')->nullable(); // Changed from phone
            $table->string('email')->nullable();
            $table->text('address')->nullable();
            $table->string('image_url')->nullable();
            $table->json('settings')->nullable(); // Village-specific settings
            $table->boolean('is_active')->default(true)->index(); // Changed from status
            $table->timestamp('established_at')->nullable();

            // PAMDes-specific fields (additional to main system)
            $table->json('pamdes_settings')->nullable(); // PAMDes-specific settings
            $table->boolean('sync_enabled')->default(true);
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamps();

            // Indexes for search functionality
            $table->index(['name', 'is_active']);
            $table->index(['slug', 'is_active']);
            $table->index('last_synced_at');
            $table->index('sync_enabled');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('villages');
    }
};
