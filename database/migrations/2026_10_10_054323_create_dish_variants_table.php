<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dish_variants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dish_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->decimal('price_delta', 10, 2)->default(0)->comment('Added to base price');
            $table->boolean('is_available')->default(true);
            $table->timestamps();

            $table->index('dish_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dish_variants');
    }
};
