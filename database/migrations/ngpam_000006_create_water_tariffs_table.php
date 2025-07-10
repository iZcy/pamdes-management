<?php
// database/migrations/2024_01_01_000002_create_water_tariffs_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('water_tariffs', function (Blueprint $table) {
            $table->id('tariff_id');
            $table->integer('usage_min');
            $table->integer('usage_max');
            $table->decimal('price_per_m3', 10, 2);
            $table->string('village_id')->nullable(); // Allow village-specific tariffs
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['village_id', 'is_active']);
            $table->index(['usage_min', 'usage_max']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('water_tariffs');
    }
};
