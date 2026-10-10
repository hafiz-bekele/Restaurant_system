<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\V1\DishResource;
use App\Models\ActivityLog;
use App\Models\Dish;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class AdminDishController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $dishes = Dish::with('category')
            ->withTrashed()
            ->when($request->filled('category'), fn ($q) => $q->where('category_id', $request->category))
            ->when($request->filled('search'), fn ($q) => $q->where('name', 'like', "%{$request->search}%"))
            ->latest()
            ->paginate(20);

        return $this->paginated($dishes->through(fn ($d) => new DishResource($d)));
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'category_id' => 'required|integer|exists:categories,id',
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'price' => 'required|numeric|min:0',
            'image' => 'nullable|string',
            'calories' => 'nullable|integer',
            'prep_time' => 'nullable|integer',
            'is_vegetarian' => 'boolean',
            'is_vegan' => 'boolean',
            'spice_level' => 'integer|between:0,3',
            'is_available' => 'boolean',
            'is_featured' => 'boolean',
        ]);

        $data['slug'] = Str::slug($data['name']).'-'.Str::random(4);
        $dish = Dish::create($data);

        ActivityLog::record('dish.created', $dish, ['name' => $dish->name]);

        return $this->success(new DishResource($dish), 'Dish created', 201);
    }

    public function show(Dish $dish): JsonResponse
    {
        return $this->success(new DishResource($dish->load('category')));
    }

    public function update(Request $request, Dish $dish): JsonResponse
    {
        $data = $request->validate([
            'category_id' => 'sometimes|integer|exists:categories,id',
            'name' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'price' => 'sometimes|numeric|min:0',
            'image' => 'nullable|string',
            'is_available' => 'boolean',
            'is_featured' => 'boolean',
            'spice_level' => 'integer|between:0,3',
            'calories' => 'nullable|integer',
            'prep_time' => 'nullable|integer',
        ]);

        $dish->update($data);
        ActivityLog::record('dish.updated', $dish, ['name' => $dish->name]);

        return $this->success(new DishResource($dish->fresh('category')), 'Dish updated');
    }

    public function destroy(Dish $dish): JsonResponse
    {
        $dish->delete();
        ActivityLog::record('dish.deleted', $dish, ['name' => $dish->name]);

        return $this->success(null, 'Dish deleted');
    }
}
