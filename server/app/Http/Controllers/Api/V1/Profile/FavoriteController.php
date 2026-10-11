<?php

namespace App\Http\Controllers\Api\V1\Profile;

use App\Http\Controllers\Controller;
use App\Http\Resources\V1\DishResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FavoriteController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $dishes = $request->user()->favorites()->with(['category', 'images'])->paginate(15);

        return $this->paginated($dishes->through(fn ($d) => new DishResource($d)));
    }

    public function store(Request $request): JsonResponse
    {
        $request->validate(['dish_id' => 'required|integer|exists:dishes,id']);
        $request->user()->favorites()->syncWithoutDetaching([$request->dish_id]);

        return $this->success(null, 'Added to favorites', 201);
    }

    public function destroy(Request $request, int $dishId): JsonResponse
    {
        $request->user()->favorites()->detach($dishId);

        return $this->success(null, 'Removed from favorites');
    }
}
