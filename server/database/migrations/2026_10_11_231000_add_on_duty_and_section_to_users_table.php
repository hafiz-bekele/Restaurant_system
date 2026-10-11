<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'on_duty')) {
                $table->boolean('on_duty')->default(true)->after('status');
            }
            if (! Schema::hasColumn('users', 'section')) {
                $table->string('section')->nullable()->after('on_duty');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['on_duty', 'section']);
        });
    }
};
