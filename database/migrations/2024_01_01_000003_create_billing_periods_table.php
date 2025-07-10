<?php
// database/migrations/2024_01_01_000003_create_billing_periods_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('billing_periods', function (Blueprint $table) {
            $table->id('period_id');
            $table->integer('year');
            $table->integer('month');
            $table->string('village_id')->nullable();
            $table->enum('status', ['active', 'inactive', 'completed'])->default('inactive');
            $table->date('reading_start_date')->nullable();
            $table->date('reading_end_date')->nullable();
            $table->date('billing_due_date')->nullable();
            $table->timestamps();

            $table->unique(['year', 'month', 'village_id']);
            $table->index(['village_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('billing_periods');
    }
};
