<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('reviews', function (Blueprint $table) {
            $table->foreignId('dish_id')->nullable()->change();
            $table->foreignId('room_id')->nullable()->after('dish_id')->constrained()->nullOnDelete();
            $table->string('title')->nullable()->after('rating');
            $table->json('images')->nullable()->after('comment');
            $table->text('admin_reply')->nullable()->after('images');
            $table->timestamp('replied_at')->nullable()->after('admin_reply');
            $table->foreignId('replied_by')->nullable()->after('replied_at')->constrained('users')->nullOnDelete();
            $table->boolean('is_featured')->default(false)->after('is_approved');
            $table->unsignedInteger('helpful_count')->default(0)->after('is_featured');

            $table->index(['room_id', 'is_approved']);
            $table->index(['is_approved', 'is_featured']);
        });

        if (Schema::hasTable('rooms')) {
            Schema::table('rooms', function (Blueprint $table) {
                if (! Schema::hasColumn('rooms', 'avg_rating')) {
                    $table->decimal('avg_rating', 3, 1)->default(0)->after('is_active');
                }
                if (! Schema::hasColumn('rooms', 'reviews_count')) {
                    $table->unsignedInteger('reviews_count')->default(0)->after('avg_rating');
                }
            });
        }

        Schema::create('review_likes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('review_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('ip_address', 45)->nullable();
            $table->timestamps();

            $table->index(['review_id', 'user_id']);
            $table->index(['review_id', 'ip_address']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('review_likes');

        if (Schema::hasTable('rooms')) {
            Schema::table('rooms', function (Blueprint $table) {
                if (Schema::hasColumn('rooms', 'reviews_count')) {
                    $table->dropColumn('reviews_count');
                }
                if (Schema::hasColumn('rooms', 'avg_rating')) {
                    $table->dropColumn('avg_rating');
                }
            });
        }

        Schema::table('reviews', function (Blueprint $table) {
            $table->dropForeign(['room_id']);
            $table->dropForeign(['replied_by']);
            $table->dropIndex(['reviews_room_id_is_approved_index']);
            $table->dropIndex(['reviews_is_approved_is_featured_index']);
            $table->dropColumn([
                'room_id',
                'title',
                'images',
                'admin_reply',
                'replied_at',
                'replied_by',
                'is_featured',
                'helpful_count',
            ]);
        });
    }
};
