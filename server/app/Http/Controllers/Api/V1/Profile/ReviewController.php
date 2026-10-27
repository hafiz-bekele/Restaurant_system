<?php

namespace App\Http\Controllers\Api\V1\Profile;

use App\Http\Controllers\Controller;
use App\Http\Requests\Profile\StoreReviewRequest;
use App\Models\Dish;
use App\Models\Review;
use App\Models\ReviewLike;
use App\Models\Room;
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
        $user = $request->user();

        $dishId = $data['dish_id'] ?? null;
        $roomId = $data['room_id'] ?? null;
        $orderId = $data['order_id'] ?? null;

        // Resolve hotel_id
        $hotelId = null;
        if ($dishId) {
            $hotelId = Dish::where('id', $dishId)->value('hotel_id');
        } elseif ($roomId) {
            $hotelId = Room::withoutGlobalScopes()->where('id', $roomId)->value('hotel_id');
        }

        // Identify existing review condition
        $conditions = [
            'user_id' => $user->id,
        ];

        if ($dishId) {
            $conditions['dish_id'] = $dishId;
            if ($orderId) {
                $conditions['order_id'] = $orderId;
            }
        } elseif ($roomId) {
            $conditions['room_id'] = $roomId;
        }

        $review = Review::updateOrCreate(
            $conditions,
            [
                'hotel_id'    => $hotelId,
                'dish_id'     => $dishId,
                'room_id'     => $roomId,
                'order_id'    => $orderId,
                'rating'      => $data['rating'],
                'title'       => $data['title'] ?? null,
                'comment'     => $data['comment'] ?? null,
                'images'      => $data['images'] ?? null,
                'is_approved' => true,
            ]
        );

        if ($dishId) {
            self::recalculateDishRating($dishId);
        }
        if ($roomId) {
            self::recalculateRoomRating($roomId);
        }

        $review->load([
            'user:id,name,avatar',
            'dish:id,name,slug,image',
            'room:id,room_number,type',
            'replier:id,name',
        ]);

        return $this->success($review, 'Thank you! Your rating and review have been published.', 201);
    }

    /**
     * Update an existing review by the author.
     */
    public function update(Request $request, Review $review): JsonResponse
    {
        $user = $request->user();
        if ($review->user_id !== $user->id && !($user->role === 'admin' || $user->hasRole('admin'))) {
            return $this->error('You are not authorized to edit this review.', 403);
        }

        $validated = $request->validate([
            'rating'   => ['sometimes', 'required', 'integer', 'between:1,5'],
            'title'    => ['nullable', 'string', 'max:150'],
            'comment'  => ['nullable', 'string', 'max:2000'],
            'images'   => ['nullable', 'array', 'max:5'],
            'images.*' => ['string', 'max:500'],
        ]);

        $review->update($validated);

        if ($review->dish_id) {
            self::recalculateDishRating($review->dish_id);
        }
        if ($review->room_id) {
            self::recalculateRoomRating($review->room_id);
        }

        $review->load([
            'user:id,name,avatar',
            'dish:id,name,slug,image',
            'room:id,room_number,type',
            'replier:id,name',
        ]);

        return $this->success($review, 'Review updated successfully.');
    }

    /**
     * Delete a review by the author.
     */
    public function destroy(Request $request, Review $review): JsonResponse
    {
        $user = $request->user();
        if ($review->user_id !== $user->id && !($user->role === 'admin' || $user->hasRole('admin'))) {
            return $this->error('You are not authorized to delete this review.', 403);
        }

        $dishId = $review->dish_id;
        $roomId = $review->room_id;

        $review->delete();

        if ($dishId) {
            self::recalculateDishRating($dishId);
        }
        if ($roomId) {
            self::recalculateRoomRating($roomId);
        }

        return $this->success(null, 'Review deleted successfully.');
    }

    /**
     * Get reviews for a specific dish with rating breakdown, filtering and sorting.
     */
    public function dishReviews(Request $request, int $dishId): JsonResponse
    {
        $dish = Dish::findOrFail($dishId);

        $query = Review::with(['user:id,name,avatar', 'replier:id,name'])
            ->where('dish_id', $dishId)
            ->where('is_approved', true);

        // Filter by rating
        if ($request->filled('rating')) {
            $query->where('rating', (int) $request->rating);
        }

        // Filter with photos only
        if ($request->boolean('with_images')) {
            $query->whereNotNull('images')->whereRaw("JSON_LENGTH(images) > 0");
        }

        // Sorting
        $sort = $request->input('sort', 'latest');
        match ($sort) {
            'highest'      => $query->orderByDesc('rating')->latest(),
            'lowest'       => $query->orderBy('rating')->latest(),
            'most_helpful' => $query->orderByDesc('helpful_count')->orderByDesc('rating'),
            default        => $query->latest(),
        };

        $reviews = $query->paginate($request->integer('per_page', 10));

        // Star breakdown (1 through 5)
        $breakdown = [];
        for ($i = 5; $i >= 1; $i--) {
            $breakdown[$i] = Review::where('dish_id', $dishId)
                ->where('is_approved', true)
                ->where('rating', $i)
                ->count();
        }

        $totalCount = Review::where('dish_id', $dishId)->where('is_approved', true)->count();
        $imagesCount = Review::where('dish_id', $dishId)
            ->where('is_approved', true)
            ->whereNotNull('images')
            ->whereRaw("JSON_LENGTH(images) > 0")
            ->count();

        $avgRating = (float) ($dish->avg_rating ?? ($totalCount > 0 ? round(Review::where('dish_id', $dishId)->where('is_approved', true)->avg('rating'), 1) : 0));

        // Mark which reviews the current user has liked
        $likedReviewIds = [];
        $userId = $request->user('sanctum')?->id;
        $ip = $request->ip();

        if ($userId) {
            $likedReviewIds = ReviewLike::whereIn('review_id', $reviews->pluck('id'))
                ->where('user_id', $userId)
                ->pluck('review_id')
                ->all();
        } elseif ($ip) {
            $likedReviewIds = ReviewLike::whereIn('review_id', $reviews->pluck('id'))
                ->where('ip_address', $ip)
                ->pluck('review_id')
                ->all();
        }

        $items = collect($reviews->items())->map(function ($rev) use ($likedReviewIds) {
            $array = $rev->toArray();
            $array['is_helpful_by_me'] = in_array($rev->id, $likedReviewIds);
            return $array;
        });

        return $this->success([
            'reviews'      => $items,
            'total'        => $reviews->total(),
            'current_page' => $reviews->currentPage(),
            'last_page'    => $reviews->lastPage(),
            'summary'      => [
                'avg_rating'    => $avgRating,
                'total_reviews' => $totalCount,
                'images_count'  => $imagesCount,
                'breakdown'     => $breakdown,
            ],
        ]);
    }

    /**
     * Get reviews for a specific room with rating breakdown, filtering and sorting.
     */
    public function roomReviews(Request $request, int $roomId): JsonResponse
    {
        $room = Room::withoutGlobalScopes()->findOrFail($roomId);

        $query = Review::with(['user:id,name,avatar', 'replier:id,name'])
            ->where('room_id', $roomId)
            ->where('is_approved', true);

        if ($request->filled('rating')) {
            $query->where('rating', (int) $request->rating);
        }

        if ($request->boolean('with_images')) {
            $query->whereNotNull('images')->whereRaw("JSON_LENGTH(images) > 0");
        }

        $sort = $request->input('sort', 'latest');
        match ($sort) {
            'highest'      => $query->orderByDesc('rating')->latest(),
            'lowest'       => $query->orderBy('rating')->latest(),
            'most_helpful' => $query->orderByDesc('helpful_count')->orderByDesc('rating'),
            default        => $query->latest(),
        };

        $reviews = $query->paginate($request->integer('per_page', 10));

        $breakdown = [];
        for ($i = 5; $i >= 1; $i--) {
            $breakdown[$i] = Review::where('room_id', $roomId)
                ->where('is_approved', true)
                ->where('rating', $i)
                ->count();
        }

        $totalCount = Review::where('room_id', $roomId)->where('is_approved', true)->count();
        $imagesCount = Review::where('room_id', $roomId)
            ->where('is_approved', true)
            ->whereNotNull('images')
            ->whereRaw("JSON_LENGTH(images) > 0")
            ->count();

        $avgRating = (float) ($room->avg_rating ?? ($totalCount > 0 ? round(Review::where('room_id', $roomId)->where('is_approved', true)->avg('rating'), 1) : 0));

        $likedReviewIds = [];
        $userId = $request->user('sanctum')?->id;
        $ip = $request->ip();

        if ($userId) {
            $likedReviewIds = ReviewLike::whereIn('review_id', $reviews->pluck('id'))
                ->where('user_id', $userId)
                ->pluck('review_id')
                ->all();
        } elseif ($ip) {
            $likedReviewIds = ReviewLike::whereIn('review_id', $reviews->pluck('id'))
                ->where('ip_address', $ip)
                ->pluck('review_id')
                ->all();
        }

        $items = collect($reviews->items())->map(function ($rev) use ($likedReviewIds) {
            $array = $rev->toArray();
            $array['is_helpful_by_me'] = in_array($rev->id, $likedReviewIds);
            return $array;
        });

        return $this->success([
            'reviews'      => $items,
            'total'        => $reviews->total(),
            'current_page' => $reviews->currentPage(),
            'last_page'    => $reviews->lastPage(),
            'summary'      => [
                'avg_rating'    => $avgRating,
                'total_reviews' => $totalCount,
                'images_count'  => $imagesCount,
                'breakdown'     => $breakdown,
            ],
        ]);
    }

    /**
     * Get featured customer testimonials for homepage.
     */
    public function featuredReviews(Request $request): JsonResponse
    {
        $reviews = Review::with([
            'user:id,name,avatar',
            'dish:id,name,slug,image',
            'room:id,room_number,type',
        ])
            ->where('is_approved', true)
            ->where(function ($q) {
                $q->where('is_featured', true)
                  ->orWhere(function ($sub) {
                      $sub->where('rating', '>=', 4)
                          ->whereNotNull('comment');
                  });
            })
            ->latest()
            ->take($request->integer('limit', 8))
            ->get();

        return $this->success($reviews);
    }

    /**
     * Toggle helpful vote (thumbs up).
     */
    public function toggleHelpful(Request $request, Review $review): JsonResponse
    {
        $userId = $request->user('sanctum')?->id;
        $ip = $request->ip();

        $query = ReviewLike::where('review_id', $review->id);
        if ($userId) {
            $query->where('user_id', $userId);
        } else {
            $query->where('ip_address', $ip);
        }

        $existing = $query->first();

        if ($existing) {
            $existing->delete();
            $review->decrement('helpful_count');
            $isHelpful = false;
        } else {
            ReviewLike::create([
                'review_id'  => $review->id,
                'user_id'    => $userId,
                'ip_address' => $ip,
            ]);
            $review->increment('helpful_count');
            $isHelpful = true;
        }

        $freshCount = max(0, (int) Review::where('id', $review->id)->value('helpful_count'));

        return $this->success([
            'is_helpful'    => $isHelpful,
            'helpful_count' => $freshCount,
        ], $isHelpful ? 'Marked as helpful.' : 'Helpful vote removed.');
    }

    /**
     * Get reviews written by the currently logged-in user.
     */
    public function myReviews(Request $request): JsonResponse
    {
        $reviews = Review::with([
            'dish:id,name,slug,price,image',
            'room:id,room_number,type',
            'replier:id,name',
        ])
            ->where('user_id', $request->user()->id)
            ->latest()
            ->paginate(15);

        return $this->paginated($reviews);
    }

    /**
     * Recalculate and update the dish average rating.
     */
    public static function recalculateDishRating(?int $dishId): void
    {
        if (!$dishId) return;
        $avg = Review::where('dish_id', $dishId)->where('is_approved', true)->avg('rating');
        Dish::where('id', $dishId)->update([
            'avg_rating' => round($avg ?? 0, 1),
        ]);
    }

    /**
     * Recalculate and update the room average rating.
     */
    public static function recalculateRoomRating(?int $roomId): void
    {
        if (!$roomId) return;
        Room::recalculateRating($roomId);
    }
}
