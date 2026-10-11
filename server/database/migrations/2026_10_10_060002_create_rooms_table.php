<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rooms', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hotel_id')->constrained()->cascadeOnDelete();
            $table->string('room_number', 20);
            $table->string('type')->default('standard'); // standard|deluxe|suite
            $table->unsignedSmallInteger('capacity')->default(2);
            $table->decimal('price_per_night', 10, 2);
            $table->string('qr_token', 64)->unique()->nullable();
            $table->json('amenities')->nullable();
            $table->string('image')->nullable();
            $table->string('status')->default('available'); // available|occupied|maintenance
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['hotel_id', 'room_number']);
        });

        Schema::create('room_bookings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hotel_id')->constrained()->cascadeOnDelete();
            $table->foreignId('room_id')->constrained()->cascadeOnDelete();
            $table->string('guest_name');
            $table->string('guest_email');
            $table->string('guest_phone', 30);
            $table->date('check_in_date');
            $table->date('check_out_date');
            $table->string('status')->default('pending'); // pending|confirmed|checked_in|checked_out|cancelled
            $table->decimal('total_amount', 10, 2)->default(0);
            $table->decimal('paid_amount', 10, 2)->default(0);
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('room_bookings');
        Schema::dropIfExists('rooms');
    }
};
