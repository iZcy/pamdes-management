<?php
// Step 1: Create a new migration to add collectors table
// Create: database/migrations/ngpam_000011_create_collectors_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('collectors', function (Blueprint $table) {
            $table->id('collector_id');
            $table->string('village_id');
            $table->string('name');
            $table->string('normalized_name'); // For duplicate detection
            $table->string('phone_number')->nullable();
            $table->string('role')->default('collector'); // collector, kasir, admin
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->foreign('village_id')->references('id')->on('villages')->onDelete('cascade');
            $table->unique(['village_id', 'normalized_name']);
            $table->index(['village_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('collectors');
    }
};
