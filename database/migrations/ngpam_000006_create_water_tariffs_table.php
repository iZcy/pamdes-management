<?php
// database/migrations/ngpam_000006_create_water_tariffs_table.php - Fixed to require village_id

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
            $table->integer('usage_max')->nullable(); // Allow null for infinite tier
            $table->decimal('price_per_m3', 10, 2);
            $table->string('village_id'); // Required - no more nullable
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            // Foreign key constraint
            $table->foreign('village_id')->references('id')->on('villages')->onDelete('cascade');

            $table->index(['village_id', 'is_active']);
            $table->index(['usage_min', 'usage_max']);

            // Ensure no overlapping ranges within the same village
            $table->unique(['village_id', 'usage_min', 'usage_max']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('water_tariffs');
    }
};
