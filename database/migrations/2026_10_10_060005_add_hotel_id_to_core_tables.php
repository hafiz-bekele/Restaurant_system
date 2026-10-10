<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * REQ-1.1: Every core entity table MUST contain a hotel_id foreign key column.
 * This migration adds hotel_id to all tables that need multi-tenant scoping.
 * nullable() used so existing rows are not broken; a seeder or deploy step sets the default.
 */
return new class extends Migration
{
    /** @var list<string> */
    private array $tables = [
        'users',
        'categories',
        'dishes',
        'tables',
        'reservations',
        'orders',
        'reviews',
        'complaint_tickets', // already has it — guard with hasColumn
        'carts',
    ];

    public function up(): void
    {
        foreach ($this->tables as $table) {
            if (! Schema::hasColumn($table, 'hotel_id')) {
                Schema::table($table, function (Blueprint $blueprint) {
                    $blueprint->foreignId('hotel_id')
                        ->nullable()
                        ->after('id')
                        ->constrained()
                        ->nullOnDelete();

                    $blueprint->index('hotel_id');
                });
            }
        }
    }

    public function down(): void
    {
        foreach ($this->tables as $table) {
            if (Schema::hasColumn($table, 'hotel_id')) {
                Schema::table($table, function (Blueprint $blueprint) use ($table) {
                    $blueprint->dropForeign(["{$table}_hotel_id_foreign"]);
                    $blueprint->dropColumn('hotel_id');
                });
            }
        }
    }
};
