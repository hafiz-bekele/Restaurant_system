<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Update tables
        Schema::table('tables', function (Blueprint $table) {
            if (! Schema::hasColumn('tables', 'features')) {
                $table->json('features')->nullable()->after('area');
            }
            if (! Schema::hasColumn('tables', 'is_active')) {
                $table->boolean('is_active')->default(true)->after('status');
                $table->index('is_active');
            }
        });

        // 2. Update reservations
        Schema::table('reservations', function (Blueprint $table) {
            if (! Schema::hasColumn('reservations', 'ulid')) {
                $table->string('ulid', 26)->nullable()->after('id');
            }
            if (! Schema::hasColumn('reservations', 'customer_name')) {
                $table->string('customer_name')->nullable()->after('ulid');
            }
            if (! Schema::hasColumn('reservations', 'start_at')) {
                $table->timestamp('start_at')->nullable()->after('party_size');
            }
            if (! Schema::hasColumn('reservations', 'end_at')) {
                $table->timestamp('end_at')->nullable()->after('start_at');
            }
            if (! Schema::hasColumn('reservations', 'duration_hours')) {
                $table->unsignedSmallInteger('duration_hours')->default(1)->after('end_at');
            }
            if (! Schema::hasColumn('reservations', 'hold_expires_at')) {
                $table->timestamp('hold_expires_at')->nullable()->after('status');
            }
            if (! Schema::hasColumn('reservations', 'price_base')) {
                $table->decimal('price_base', 10, 2)->default(0.00)->after('hold_expires_at');
            }
            if (! Schema::hasColumn('reservations', 'surcharge_percent')) {
                $table->decimal('surcharge_percent', 5, 2)->default(0.00)->after('price_base');
            }
            if (! Schema::hasColumn('reservations', 'surcharge_amount')) {
                $table->decimal('surcharge_amount', 10, 2)->default(0.00)->after('surcharge_percent');
            }
            if (! Schema::hasColumn('reservations', 'total_price')) {
                $table->decimal('total_price', 10, 2)->default(0.00)->after('surcharge_amount');
            }
            if (! Schema::hasColumn('reservations', 'currency')) {
                $table->string('currency', 3)->default('ETB')->after('total_price');
            }
            if (! Schema::hasColumn('reservations', 'special_requests')) {
                $table->text('special_requests')->nullable()->after('currency');
            }
            if (! Schema::hasColumn('reservations', 'paid_at')) {
                $table->timestamp('paid_at')->nullable()->after('special_requests');
            }
        });

        // Populate ULIDs and default dates for existing reservations
        $existing = DB::table('reservations')->whereNull('ulid')->get();
        foreach ($existing as $row) {
            $start = $row->reserved_at ? $row->reserved_at : now()->toDateTimeString();
            $durationMinutes = $row->duration ?? 60;
            $durationHours = max(1, (int) round($durationMinutes / 60));
            $end = date('Y-m-d H:i:s', strtotime($start . " +{$durationMinutes} minutes"));

            DB::table('reservations')->where('id', $row->id)->update([
                'ulid'           => (string) Str::ulid(),
                'customer_name'  => $row->name ?? 'Guest Diner',
                'start_at'       => $start,
                'end_at'         => $end,
                'duration_hours' => $durationHours,
                'total_price'    => $row->reservation_fee ?? 50.00,
                'price_base'     => $row->reservation_fee ?? 50.00,
                'currency'       => 'ETB',
            ]);
        }

        // Add indexes to reservations
        Schema::table('reservations', function (Blueprint $table) {
            $table->unique('ulid');
            $table->index(['table_id', 'start_at', 'end_at']);
            $table->index(['status', 'hold_expires_at']);
            $table->index(['end_at', 'status']);
        });

        // 3. Update payments table to support reservations
        Schema::table('payments', function (Blueprint $table) {
            // make order_id nullable
            $table->foreignId('order_id')->nullable()->change();

            if (! Schema::hasColumn('payments', 'reservation_id')) {
                $table->foreignId('reservation_id')->nullable()->after('order_id')->constrained('reservations')->nullOnDelete();
                $table->index('reservation_id');
            }
            if (! Schema::hasColumn('payments', 'tx_ref')) {
                $table->string('tx_ref')->nullable()->unique()->after('transaction_id');
            }
            if (! Schema::hasColumn('payments', 'chapa_reference')) {
                $table->string('chapa_reference')->nullable()->after('tx_ref');
            }
            if (! Schema::hasColumn('payments', 'raw_payload')) {
                $table->json('raw_payload')->nullable()->after('payload');
            }
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            if (Schema::hasColumn('payments', 'reservation_id')) {
                $table->dropForeign(['reservation_id']);
                $table->dropColumn(['reservation_id']);
            }
            $table->dropColumn(['tx_ref', 'chapa_reference', 'raw_payload']);
        });

        Schema::table('reservations', function (Blueprint $table) {
            $table->dropIndex(['table_id', 'start_at', 'end_at']);
            $table->dropIndex(['status', 'hold_expires_at']);
            $table->dropIndex(['end_at', 'status']);
            $table->dropUnique(['ulid']);
            $table->dropColumn([
                'ulid', 'customer_name', 'start_at', 'end_at', 'duration_hours',
                'hold_expires_at', 'price_base', 'surcharge_percent',
                'surcharge_amount', 'total_price', 'currency', 'special_requests', 'paid_at',
            ]);
        });

        Schema::table('tables', function (Blueprint $table) {
            $table->dropColumn(['features', 'is_active']);
        });
    }
};
