<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreDishRequest;
use App\Http\Requests\Admin\UpdateDishRequest;
use App\Http\Resources\V1\DishResource;
use App\Models\ActivityLog;
use App\Models\Dish;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class AdminDishController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $status = $request->query('status', 'active');

        $dishes = Dish::with('category')
            ->when($status === 'trashed', fn ($q) => $q->onlyTrashed())
            ->when($status === 'all', fn ($q) => $q->withTrashed())
            ->when($status === 'active' || empty($status), fn ($q) => $q->withoutTrashed())
            ->when($request->filled('category'), fn ($q) => $q->where('category_id', $request->category))
            ->when($request->filled('search'), fn ($q) => $q->where(function ($sub) use ($request) {
                $sub->where('name', 'like', "%{$request->search}%")
                    ->orWhere('name_am', 'like', "%{$request->search}%")
                    ->orWhere('description', 'like', "%{$request->search}%")
                    ->orWhere('description_am', 'like', "%{$request->search}%");
            }))
            ->latest()
            ->paginate(24);

        return $this->paginated($dishes->through(fn ($d) => new DishResource($d)));
    }

    public function store(StoreDishRequest $request): JsonResponse
    {
        $data         = $request->validated();
        $data['slug'] = Str::slug($data['name']).'-'.Str::random(4);

        if ($request->hasFile('image')) {
            $path = $request->file('image')->store('dishes', 'public');
            $data['image'] = Storage::disk('public')->url($path);
        } elseif (!empty($data['image_url']) && empty($data['image'])) {
            $data['image'] = $data['image_url'];
        }
        unset($data['image_url']);

        $dish = Dish::create($data);
        ActivityLog::record('dish.created', $dish, ['name' => $dish->name]);

        return $this->success(new DishResource($dish->load('category')), 'Dish created', 201);
    }

    public function show(Dish $dish): JsonResponse
    {
        return $this->success(new DishResource($dish->load('category')));
    }

    public function update(UpdateDishRequest $request, Dish $dish): JsonResponse
    {
        $data = $request->validated();

        if ($request->hasFile('image')) {
            if ($dish->image && Str::contains($dish->image, '/storage/dishes/')) {
                $oldPath = Str::after($dish->image, '/storage/');
                Storage::disk('public')->delete($oldPath);
            }
            $path = $request->file('image')->store('dishes', 'public');
            $data['image'] = Storage::disk('public')->url($path);
        } elseif (!empty($data['image_url']) && empty($data['image'])) {
            $data['image'] = $data['image_url'];
        }
        unset($data['image_url']);

        $dish->update($data);
        ActivityLog::record('dish.updated', $dish, ['name' => $dish->name]);

        return $this->success(new DishResource($dish->fresh('category')), 'Dish updated');
    }

    public function destroy(Request $request, int|string $id): JsonResponse
    {
        $dish = Dish::withTrashed()->findOrFail($id);

        if ($request->boolean('force') || $dish->trashed()) {
            $name = $dish->name;
            $dish->forceDelete();
            ActivityLog::record('dish.force_deleted', null, ['id' => $id, 'name' => $name]);

            return $this->success(null, 'Dish permanently deleted');
        }

        $dish->delete();
        ActivityLog::record('dish.deleted', $dish, ['name' => $dish->name]);

        return $this->success(null, 'Dish deleted');
    }

    public function restore(int|string $id): JsonResponse
    {
        $dish = Dish::onlyTrashed()->findOrFail($id);
        $dish->restore();
        ActivityLog::record('dish.restored', $dish, ['name' => $dish->name]);

        return $this->success(new DishResource($dish->fresh('category')), 'Dish restored');
    }
}
