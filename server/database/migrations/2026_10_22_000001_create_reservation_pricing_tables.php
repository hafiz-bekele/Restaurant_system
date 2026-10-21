<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reservation_pricing', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('duration_hours')->unique();
            $table->decimal('price', 10, 2);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index('is_active');
        });

        Schema::create('reservation_day_surcharges', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('days_ahead')->unique()->comment('0 = today, 1 = tomorrow, 2 = 2+ days');
            $table->decimal('percent', 5, 2);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reservation_day_surcharges');
        Schema::dropIfExists('reservation_pricing');
    }
};
