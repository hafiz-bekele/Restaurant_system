<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reservations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('table_id')->constrained()->restrictOnDelete();
            $table->string('name');
            $table->string('phone');
            $table->string('email');
            $table->unsignedSmallInteger('party_size');
            $table->timestamp('reserved_at');
            $table->unsignedSmallInteger('duration')->default(90)->comment('minutes');
            $table->string('status')->default('pending');
            $table->text('notes')->nullable();
            $table->softDeletes();
            $table->timestamps();

            $table->index(['reserved_at', 'table_id']);
            $table->index(['status', 'reserved_at']);
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reservations');
    }
};
