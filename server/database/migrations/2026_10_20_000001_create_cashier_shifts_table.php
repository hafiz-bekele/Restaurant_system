<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cashier_shifts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hotel_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->decimal('opening_float', 10, 2)->default(0);
            $table->decimal('closing_cash_counted', 10, 2)->nullable();
            $table->decimal('expected_cash', 10, 2)->nullable();
            $table->decimal('variance', 10, 2)->nullable();
            $table->decimal('cash_sales', 10, 2)->default(0);
            $table->decimal('digital_sales', 10, 2)->default(0);
            $table->decimal('room_charge_sales', 10, 2)->default(0);
            $table->decimal('total_sales', 10, 2)->default(0);
            $table->integer('orders_count')->default(0);
            $table->string('status')->default('open'); // open, closed
            $table->text('opening_notes')->nullable();
            $table->text('closing_notes')->nullable();
            $table->timestamp('opened_at')->useCurrent();
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status']);
        });

        Schema::create('cash_drawer_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cashier_shift_id')->constrained('cashier_shifts')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('type')->comment('pay_in|pay_out|drop');
            $table->decimal('amount', 10, 2);
            $table->string('reason');
            $table->timestamps();

            $table->index('cashier_shift_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cash_drawer_transactions');
        Schema::dropIfExists('cashier_shifts');
    }
};
