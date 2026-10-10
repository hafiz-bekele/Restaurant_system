<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cart_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cart_id')->constrained()->cascadeOnDelete();
            $table->foreignId('dish_id')->constrained()->cascadeOnDelete();
            $table->foreignId('variant_id')->nullable()->constrained('dish_variants')->nullOnDelete();
            $table->unsignedSmallInteger('quantity')->default(1);
            $table->json('options')->nullable()->comment('Selected addons as JSON');
            $table->text('note')->nullable();
            $table->timestamps();

            $table->index('cart_id');
            $table->index('dish_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cart_items');
    }
};
