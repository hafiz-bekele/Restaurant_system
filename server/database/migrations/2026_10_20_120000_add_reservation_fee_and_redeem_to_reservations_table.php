<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reservations', function (Blueprint $table) {
            $table->decimal('reservation_fee', 10, 2)->default(50.00)->after('duration');
            $table->decimal('initial_fee', 10, 2)->default(50.00)->after('reservation_fee');
            $table->string('fee_status')->default('pending')->after('initial_fee'); // pending, paid, redeemed, waived
            $table->timestamp('redeemed_at')->nullable()->after('fee_status');
            $table->decimal('redeem_amount', 10, 2)->nullable()->after('redeemed_at');
        });
    }

    public function down(): void
    {
        Schema::table('reservations', function (Blueprint $table) {
            $table->dropColumn(['reservation_fee', 'initial_fee', 'fee_status', 'redeemed_at', 'redeem_amount']);
        });
    }
};
