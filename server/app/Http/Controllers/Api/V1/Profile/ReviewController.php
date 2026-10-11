<?php

namespace App\Http\Controllers\Api\V1\Profile;

use App\Http\Controllers\Controller;
use App\Http\Requests\Profile\StoreReviewRequest;
use App\Models\Dish;
use App\Models\Review;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReviewController extends Controller
{
    /**
     * Store or update a customer review.
     */
    public function store(StoreReviewRequest $request): JsonResponse
    {
        $data = $request->validated();
        $userId = $request->user()->id;

        // Condition to identify existing review by this user
        $conditions = [
            'user_id' => $userId,
            'dish_id' => $data['dish_id'],
        ];

        if (!empty($data['order_id'])) {
            $conditions['order_id'] = $data['order_id'];
        }

        $review = Review::updateOrCreate(
            $conditions,
            [
                'order_id'    => $data['order_id'] ?? null,
                'rating'      => $data['rating'],
                'comment'     => $data['comment'] ?? null,
                'is_approved' => true,
            ]
        );

        // Recalculate average rating and cache on Dish
        self::recalculateDishRating($data['dish_id']);

        $review->load(['user:id,name,avatar', 'dish:id,name,slug']);

        return $this->success($review, 'Thank you! Your rating and review have been published.', 201);
    }

    /**
     * Get reviews for a specific dish with rating breakdown.
     */
    public function dishReviews(Request $request, int $dishId): JsonResponse
    {
        $dish = Dish::findOrFail($dishId);

        $query = Review::with('user:id,name,avatar')
            ->where('dish_id', $dishId)
            ->where('is_approved', true)
            ->latest();

        $reviews = $query->paginate(10);

        // Star breakdown (1 through 5)
        $breakdown = [];
        for ($i = 5; $i >= 1; $i--) {
            $breakdown[$i] = Review::where('dish_id', $dishId)
                ->where('is_approved', true)
                ->where('rating', $i)
                ->count();
        }

        $totalCount = Review::where('dish_id', $dishId)->where('is_approved', true)->count();
        $avgRating = (float) ($dish->avg_rating ?? ($totalCount > 0 ? round(Review::where('dish_id', $dishId)->where('is_approved', true)->avg('rating'), 1) : 0));

        return $this->success([
            'reviews'     => $reviews->items(),
            'total'       => $reviews->total(),
            'current_page'=> $reviews->currentPage(),
            'last_page'   => $reviews->lastPage(),
            'summary'     => [
                'avg_rating'   => $avgRating,
                'total_reviews'=> $totalCount,
                'breakdown'    => $breakdown,
            ],
        ]);
    }

    /**
     * Get reviews written by the currently logged-in user.
     */
    public function myReviews(Request $request): JsonResponse
    {
        $reviews = Review::with('dish:id,name,slug,price,image')
            ->where('user_id', $request->user()->id)
            ->latest()
            ->paginate(15);

        return $this->paginated($reviews);
    }

    /**
     * Recalculate and update the dish average rating.
     */
    public static function recalculateDishRating(int $dishId): void
    {
        $avg = Review::where('dish_id', $dishId)->where('is_approved', true)->avg('rating');
        Dish::where('id', $dishId)->update([
            'avg_rating' => round($avg ?? 0, 1),
        ]);
    }
}
