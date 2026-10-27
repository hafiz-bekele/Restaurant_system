<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Dish;
use App\Models\Hotel;
use App\Models\Review;
use App\Models\Room;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReviewTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private User $admin;
    private Hotel $hotel;
    private Dish $dish;
    private Room $room;

    protected function setUp(): void
    {
        parent::setUp();

        $this->hotel = Hotel::create([
            'name' => 'Grand Palace Hotel',
            'slug' => 'grand-palace',
            'is_active' => true,
        ]);

        $this->seed(\Database\Seeders\RoleSeeder::class);

        $this->user = User::factory()->create([
            'hotel_id' => $this->hotel->id,
        ]);
        $this->user->assignRole('customer');

        $this->admin = User::factory()->create([
            'hotel_id' => $this->hotel->id,
        ]);
        $this->admin->assignRole('admin');

        $category = Category::create([
            'hotel_id' => $this->hotel->id,
            'name' => 'Main Courses',
            'slug' => 'main-courses',
        ]);

        $this->dish = Dish::create([
            'hotel_id' => $this->hotel->id,
            'category_id' => $category->id,
            'name' => 'Truffle Pasta',
            'slug' => 'truffle-pasta',
            'price' => 24.50,
            'is_available' => true,
        ]);

        $this->room = Room::create([
            'hotel_id' => $this->hotel->id,
            'room_number' => '101',
            'type' => 'deluxe',
            'price_per_night' => 120.00,
            'capacity' => 2,
            'is_active' => true,
        ]);
    }

    public function test_customer_can_create_dish_review_with_title_and_images(): void
    {
        $response = $this->actingAs($this->user)->postJson('/api/v1/reviews', [
            'dish_id' => $this->dish->id,
            'rating'  => 5,
            'title'   => 'Absolute culinary perfection',
            'comment' => 'The truffle sauce was rich and delicious.',
            'images'  => ['https://images.unsplash.com/photo-1546069901-ba9599a7e63c'],
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('reviews', [
            'dish_id' => $this->dish->id,
            'user_id' => $this->user->id,
            'rating'  => 5,
            'title'   => 'Absolute culinary perfection',
        ]);

        $this->dish->refresh();
        $this->assertEquals(5.0, (float) $this->dish->avg_rating);
    }

    public function test_customer_can_create_room_review(): void
    {
        $response = $this->actingAs($this->user)->postJson('/api/v1/reviews', [
            'room_id' => $this->room->id,
            'rating'  => 4,
            'title'   => 'Very cozy and quiet room',
            'comment' => 'The bed was very comfortable.',
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('reviews', [
            'room_id' => $this->room->id,
            'user_id' => $this->user->id,
            'rating'  => 4,
        ]);

        $this->room->refresh();
        $this->assertEquals(4.0, (float) $this->room->avg_rating);
        $this->assertEquals(1, $this->room->reviews_count);
    }

    public function test_guest_or_user_can_toggle_helpful_vote(): void
    {
        $review = Review::create([
            'hotel_id'    => $this->hotel->id,
            'user_id'     => $this->user->id,
            'dish_id'     => $this->dish->id,
            'rating'      => 5,
            'comment'     => 'Awesome!',
            'is_approved' => true,
        ]);

        // First vote: helpful increments
        $res1 = $this->actingAs($this->user)->postJson("/api/v1/reviews/{$review->id}/helpful");
        $res1->assertStatus(200)->assertJsonPath('data.helpful_count', 1);

        // Second vote: unhelpful / toggle off
        $res2 = $this->actingAs($this->user)->postJson("/api/v1/reviews/{$review->id}/helpful");
        $res2->assertStatus(200)->assertJsonPath('data.helpful_count', 0);
    }

    public function test_admin_can_reply_to_review(): void
    {
        $review = Review::create([
            'hotel_id'    => $this->hotel->id,
            'user_id'     => $this->user->id,
            'dish_id'     => $this->dish->id,
            'rating'      => 5,
            'comment'     => 'Loved it!',
            'is_approved' => true,
        ]);

        $response = $this->actingAs($this->admin)->postJson("/api/v1/admin/reviews/{$review->id}/reply", [
            'admin_reply' => 'Thank you for your kind words! We look forward to seeing you again.',
        ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('reviews', [
            'id'          => $review->id,
            'admin_reply' => 'Thank you for your kind words! We look forward to seeing you again.',
            'replied_by'  => $this->admin->id,
        ]);
    }

    public function test_admin_can_perform_bulk_actions(): void
    {
        $review1 = Review::create([
            'hotel_id'    => $this->hotel->id,
            'user_id'     => $this->user->id,
            'dish_id'     => $this->dish->id,
            'rating'      => 5,
            'is_approved' => false,
        ]);

        $review2 = Review::create([
            'hotel_id'    => $this->hotel->id,
            'user_id'     => $this->user->id,
            'dish_id'     => $this->dish->id,
            'rating'      => 4,
            'is_approved' => false,
        ]);

        $response = $this->actingAs($this->admin)->postJson('/api/v1/admin/reviews/bulk', [
            'action' => 'approve',
            'ids'    => [$review1->id, $review2->id],
        ]);

        $response->assertStatus(200);
        $this->assertTrue($review1->fresh()->is_approved);
        $this->assertTrue($review2->fresh()->is_approved);
    }
}
