<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reservations', function (Blueprint $table) {
            $table->string('payment_method')->nullable()->default('chapa')->after('fee_status');
            $table->string('transaction_id')->nullable()->after('payment_method');
            $table->string('payment_status')->default('pending')->after('transaction_id');
            $table->boolean('is_approved')->default(false)->after('payment_status');
            $table->timestamp('approved_at')->nullable()->after('is_approved');
            $table->foreignId('approved_by')->nullable()->after('approved_at')->constrained('users')->nullOnDelete();
            $table->string('rejection_reason')->nullable()->after('approved_by');
        });
    }

    public function down(): void
    {
        Schema::table('reservations', function (Blueprint $table) {
            $table->dropForeign(['approved_by']);
            $table->dropColumn([
                'payment_method',
                'transaction_id',
                'payment_status',
                'is_approved',
                'approved_at',
                'approved_by',
                'rejection_reason',
            ]);
        });
    }
};
