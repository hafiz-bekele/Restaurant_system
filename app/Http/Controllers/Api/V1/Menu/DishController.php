<?php

namespace App\Http\Controllers\Api\V1\Menu;

use App\Http\Controllers\Controller;
use App\Http\Resources\V1\DishResource;
use App\Models\Dish;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DishController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Dish::with(['category', 'images'])
            ->withCount('reviews')
            ->available();

        if ($request->filled('category')) {
            $query->whereHas('category', fn ($q) => $q->where('slug', $request->category));
        }

        if ($request->filled('search')) {
            $query->where(fn ($q) => $q
                ->where('name', 'like', "%{$request->search}%")
                ->orWhere('description', 'like', "%{$request->search}%")
            );
        }

        if ($request->boolean('vegetarian')) {
            $query->where('is_vegetarian', true);
        }

        if ($request->boolean('vegan')) {
            $query->where('is_vegan', true);
        }

        if ($request->filled('min_price')) {
            $query->where('price', '>=', $request->min_price);
        }

        if ($request->filled('max_price')) {
            $query->where('price', '<=', $request->max_price);
        }

        if ($request->filled('spice_level')) {
            $query->where('spice_level', $request->spice_level);
        }

        $sort = $request->get('sort', 'popular');
        match ($sort) {
            'price_asc' => $query->orderBy('price'),
            'price_desc' => $query->orderByDesc('price'),
            'rating' => $query->orderByDesc('avg_rating'),
            default => $query->orderByDesc('orders_count'),
        };

        $dishes = $query->cursorPaginate(15);

        return $this->paginated($dishes->through(fn ($d) => new DishResource($d)));
    }

    public function show(string $slug): JsonResponse
    {
        $dish = Dish::with([
            'category', 'images', 'variants', 'addonGroups.addons', 'allergens',
            'reviews' => fn ($q) => $q->where('is_approved', true)->with('user:id,name,avatar')->latest()->limit(10),
        ])
            ->withCount(['reviews' => fn ($q) => $q->where('is_approved', true)])
            ->where('slug', $slug)
            ->available()
            ->firstOrFail();

        return $this->success(new DishResource($dish));
    }

    public function featured(): JsonResponse
    {
        $dishes = Dish::with(['category', 'images'])->featured()->limit(8)->get();

        return $this->success(DishResource::collection($dishes));
    }

    public function search(Request $request): JsonResponse
    {
        $request->validate(['q' => 'required|string|min:2|max:100']);

        $results = Dish::search($request->q)
            ->where('is_available', true)
            ->take(10)
            ->get()
            ->load('category');

        return $this->success(DishResource::collection($results));
    }
}
