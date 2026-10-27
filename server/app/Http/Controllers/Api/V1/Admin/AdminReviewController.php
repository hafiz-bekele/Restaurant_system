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
     * List all reviews with multi-dimensional filters, sorting, and aggregate stats.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Review::with([
            'user:id,name,email,avatar',
            'dish:id,name,slug,image,avg_rating',
            'room:id,room_number,type',
            'order:id,order_number',
            'replier:id,name',
        ]);

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

        // Filter by entity type (dish or room)
        if ($request->filled('type')) {
            if ($request->type === 'dish') {
                $query->whereNotNull('dish_id');
            } elseif ($request->type === 'room') {
                $query->whereNotNull('room_id');
            }
        }

        // Filter by target dish
        if ($request->filled('dish_id')) {
            $query->where('dish_id', $request->dish_id);
        }

        // Filter by target room
        if ($request->filled('room_id')) {
            $query->where('room_id', $request->room_id);
        }

        // Filter by management reply status
        if ($request->filled('has_reply')) {
            if ($request->has_reply === 'yes' || $request->has_reply === '1') {
                $query->whereNotNull('admin_reply');
            } elseif ($request->has_reply === 'no' || $request->has_reply === '0') {
                $query->whereNull('admin_reply');
            }
        }

        // Filter by photo attachments
        if ($request->filled('has_images')) {
            if ($request->has_images === 'yes' || $request->has_images === '1') {
                $query->whereNotNull('images')->whereRaw("JSON_LENGTH(images) > 0");
            } elseif ($request->has_images === 'no' || $request->has_images === '0') {
                $query->where(function ($q) {
                    $q->whereNull('images')->orWhereRaw("JSON_LENGTH(images) = 0");
                });
            }
        }

        // Filter by featured
        if ($request->filled('featured')) {
            $query->where('is_featured', filter_var($request->featured, FILTER_VALIDATE_BOOLEAN));
        }

        // Search text across comment, title, diner, dish, room, or order
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('comment', 'like', "%{$search}%")
                  ->orWhere('title', 'like', "%{$search}%")
                  ->orWhereHas('user', fn ($u) => $u->where('name', 'like', "%{$search}%")->orWhere('email', 'like', "%{$search}%"))
                  ->orWhereHas('dish', fn ($d) => $d->where('name', 'like', "%{$search}%"))
                  ->orWhereHas('room', fn ($r) => $r->where('room_number', 'like', "%{$search}%"))
                  ->orWhereHas('order', fn ($o) => $o->where('order_number', 'like', "%{$search}%"));
            });
        }

        // Sorting
        $sort = $request->input('sort', 'latest');
        match ($sort) {
            'oldest'       => $query->oldest(),
            'rating_desc'  => $query->orderByDesc('rating')->latest(),
            'rating_asc'   => $query->orderBy('rating')->latest(),
            'helpful'      => $query->orderByDesc('helpful_count')->latest(),
            default        => $query->latest(),
        };

        $reviews = $query->paginate($request->integer('per_page', 15));

        // Aggregate statistics for dashboard badges
        $totalReviews = Review::count();
        $approvedCount = Review::where('is_approved', true)->count();
        $pendingCount = Review::where('is_approved', false)->count();
        $repliedCount = Review::whereNotNull('admin_reply')->count();
        $withImagesCount = Review::whereNotNull('images')->whereRaw("JSON_LENGTH(images) > 0")->count();
        $avgRating = round(Review::where('is_approved', true)->avg('rating') ?? 0, 1);
        $fiveStarCount = Review::where('rating', 5)->count();

        $stats = [
            'total_reviews'     => $totalReviews,
            'approved_count'    => $approvedCount,
            'pending_count'     => $pendingCount,
            'replied_count'     => $repliedCount,
            'reply_rate'        => $totalReviews > 0 ? round(($repliedCount / $totalReviews) * 100) : 0,
            'with_images_count' => $withImagesCount,
            'avg_rating'        => $avgRating,
            'five_star_count'   => $fiveStarCount,
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
     * Approve, unapprove, or feature/pin a review.
     */
    public function update(Request $request, Review $review): JsonResponse
    {
        $validated = $request->validate([
            'is_approved' => ['sometimes', 'boolean'],
            'is_featured' => ['sometimes', 'boolean'],
        ]);

        $review->update($validated);

        // Recalculate target rating
        if ($review->dish_id) {
            ReviewController::recalculateDishRating($review->dish_id);
        }
        if ($review->room_id) {
            ReviewController::recalculateRoomRating($review->room_id);
        }

        return $this->success(
            $review->load(['user:id,name,email', 'dish:id,name', 'room:id,room_number', 'replier:id,name']),
            'Review updated successfully.'
        );
    }

    /**
     * Submit or update management official response.
     */
    public function reply(Request $request, Review $review): JsonResponse
    {
        $validated = $request->validate([
            'admin_reply' => ['required', 'string', 'max:2000'],
        ]);

        $review->update([
            'admin_reply' => $validated['admin_reply'],
            'replied_at'  => now(),
            'replied_by'  => $request->user()->id,
        ]);

        return $this->success(
            $review->load(['user:id,name,email', 'dish:id,name', 'room:id,room_number', 'replier:id,name']),
            'Management reply posted successfully.'
        );
    }

    /**
     * Delete management response from a review.
     */
    public function deleteReply(Review $review): JsonResponse
    {
        $review->update([
            'admin_reply' => null,
            'replied_at'  => null,
            'replied_by'  => null,
        ]);

        return $this->success(
            $review->load(['user:id,name,email', 'dish:id,name', 'room:id,room_number']),
            'Management reply removed.'
        );
    }

    /**
     * Execute bulk moderation actions on reviews.
     */
    public function bulkAction(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'action' => ['required', 'string', 'in:approve,hide,feature,unfeature,delete'],
            'ids'    => ['required', 'array', 'min:1'],
            'ids.*'  => ['integer', 'exists:reviews,id'],
        ]);

        $ids = $validated['ids'];
        $action = $validated['action'];

        $reviews = Review::whereIn('id', $ids)->get();
        $dishIds = $reviews->pluck('dish_id')->filter()->unique();
        $roomIds = $reviews->pluck('room_id')->filter()->unique();

        match ($action) {
            'approve'   => Review::whereIn('id', $ids)->update(['is_approved' => true]),
            'hide'      => Review::whereIn('id', $ids)->update(['is_approved' => false]),
            'feature'   => Review::whereIn('id', $ids)->update(['is_featured' => true]),
            'unfeature' => Review::whereIn('id', $ids)->update(['is_featured' => false]),
            'delete'    => Review::whereIn('id', $ids)->delete(),
        };

        foreach ($dishIds as $dId) {
            ReviewController::recalculateDishRating($dId);
        }
        foreach ($roomIds as $rId) {
            ReviewController::recalculateRoomRating($rId);
        }

        return $this->success(null, "Bulk action '{$action}' applied to " . count($ids) . ' reviews.');
    }

    /**
     * Delete a review permanently.
     */
    public function destroy(Review $review): JsonResponse
    {
        $dishId = $review->dish_id;
        $roomId = $review->room_id;

        $review->delete();

        if ($dishId) {
            ReviewController::recalculateDishRating($dishId);
        }
        if ($roomId) {
            ReviewController::recalculateRoomRating($roomId);
        }

        return $this->success(null, 'Review permanently deleted.');
    }
}
