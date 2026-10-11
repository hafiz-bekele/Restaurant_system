<?php

namespace App\Http\Controllers\Api\V1\Profile;

use App\Http\Controllers\Controller;
use App\Http\Requests\Profile\StoreReviewRequest;
use App\Models\Dish;
use App\Models\Review;
use Illuminate\Http\JsonResponse;

class ReviewController extends Controller
{
    public function store(StoreReviewRequest $request): JsonResponse
    {
        $data = $request->validated();

        $review = Review::updateOrCreate(
            [
                'user_id'  => $request->user()->id,
                'dish_id'  => $data['dish_id'],
                'order_id' => $data['order_id'],
            ],
            [
                'rating'      => $data['rating'],
                'comment'     => $data['comment'] ?? null,
                'is_approved' => false,
            ]
        );

        $avg = Review::where('dish_id', $data['dish_id'])->where('is_approved', true)->avg('rating');
        Dish::where('id', $data['dish_id'])->update(['avg_rating' => round($avg ?? 0, 2)]);

        return $this->success($review, 'Review submitted — pending approval', 201);
    }
}
