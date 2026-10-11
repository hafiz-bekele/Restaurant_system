<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Api\V1\Profile\ReviewController;
use App\Models\Review;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminReviewController extends Controller
{
    /**
     * List all reviews with filters and summary stats.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Review::with([
            'user:id,name,email,avatar',
            'dish:id,name,slug,image,avg_rating',
            'order:id,order_number',
        ])->latest();

        // Filter by status (approved / pending)
        if ($request->filled('status')) {
            if ($request->status === 'approved') {
                $query->where('is_approved', true);
            } elseif ($request->status === 'pending') {
                $query->where('is_approved', false);
            }
        }

        // Filter by rating
        if ($request->filled('rating')) {
            $query->where('rating', (int) $request->rating);
        }

        // Filter by dish
        if ($request->filled('dish_id')) {
            $query->where('dish_id', $request->dish_id);
        }

        // Search text
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('comment', 'like', "%{$search}%")
                  ->orWhereHas('user', fn ($u) => $u->where('name', 'like', "%{$search}%")->orWhere('email', 'like', "%{$search}%"))
                  ->orWhereHas('dish', fn ($d) => $d->where('name', 'like', "%{$search}%"));
            });
        }

        $reviews = $query->paginate($request->integer('per_page', 15));

        // Aggregate statistics for dashboard badges
        $stats = [
            'total_reviews' => Review::count(),
            'approved_count'=> Review::where('is_approved', true)->count(),
            'pending_count' => Review::where('is_approved', false)->count(),
            'avg_rating'    => round(Review::avg('rating') ?? 0, 1),
        ];

        return response()->json([
            'status' => 'success',
            'data'   => $reviews->items(),
            'meta'   => [
                'current_page' => $reviews->currentPage(),
                'last_page'    => $reviews->lastPage(),
                'per_page'     => $reviews->perPage(),
                'total'        => $reviews->total(),
            ],
            'stats'  => $stats,
        ]);
    }

    /**
     * Approve or unapprove a review.
     */
    public function update(Request $request, Review $review): JsonResponse
    {
        $validated = $request->validate([
            'is_approved' => ['required', 'boolean'],
        ]);

        $review->update([
            'is_approved' => $validated['is_approved'],
        ]);

        // Recalculate dish rating
        ReviewController::recalculateDishRating($review->dish_id);

        $statusText = $review->is_approved ? 'approved' : 'hidden';

        return $this->success($review->load(['user:id,name,email', 'dish:id,name']), "Review marked as {$statusText}.");
    }

    /**
     * Delete a review.
     */
    public function destroy(Review $review): JsonResponse
    {
        $dishId = $review->dish_id;
        $review->delete();

        // Recalculate dish rating
        ReviewController::recalculateDishRating($dishId);

        return $this->success(null, 'Review permanently deleted.');
    }
}
