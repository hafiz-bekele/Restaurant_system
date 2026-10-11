<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Hotel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class AdminHotelController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $hotels = Hotel::withCount(['rooms', 'users', 'orders'])
            ->when($request->filled('search'), function ($q) use ($request) {
                $q->where('name', 'like', "%{$request->search}%")
                  ->orWhere('email', 'like', "%{$request->search}%");
            })
            ->latest()
            ->get();

        return $this->success($hotels);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name'                => ['required', 'string', 'max:255'],
            'currency'            => ['nullable', 'string', 'max:10'],
            'vat_rate'            => ['nullable', 'numeric', 'min:0', 'max:100'],
            'service_charge_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'phone'               => ['nullable', 'string', 'max:50'],
            'email'               => ['nullable', 'email', 'max:255'],
            'address'             => ['nullable', 'string'],
            'logo'                => ['nullable', 'string'],
            'cover_image'         => ['nullable', 'string'],
            'is_active'           => ['nullable', 'boolean'],
        ]);

        $data['slug'] = Str::slug($data['name']) . '-' . Str::random(4);
        $data['currency'] = $data['currency'] ?? 'ETB';
        $data['vat_rate'] = $data['vat_rate'] ?? 15.00;
        $data['service_charge_rate'] = $data['service_charge_rate'] ?? 10.00;
        $data['is_active'] = $data['is_active'] ?? true;

        $hotel = Hotel::create($data);

        ActivityLog::record('hotel.created', $hotel, ['name' => $hotel->name]);

        return $this->success($hotel, 'Hotel created successfully', 201);
    }

    public function show(Hotel $hotel): JsonResponse
    {
        $hotel->loadCount(['rooms', 'users', 'orders']);
        return $this->success($hotel);
    }

    public function update(Request $request, Hotel $hotel): JsonResponse
    {
        $data = $request->validate([
            'name'                => ['sometimes', 'string', 'max:255'],
            'currency'            => ['nullable', 'string', 'max:10'],
            'vat_rate'            => ['nullable', 'numeric', 'min:0', 'max:100'],
            'service_charge_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'phone'               => ['nullable', 'string', 'max:50'],
            'email'               => ['nullable', 'email', 'max:255'],
            'address'             => ['nullable', 'string'],
            'logo'                => ['nullable', 'string'],
            'cover_image'         => ['nullable', 'string'],
            'is_active'           => ['nullable', 'boolean'],
        ]);

        $hotel->update($data);

        ActivityLog::record('hotel.updated', $hotel, ['name' => $hotel->name]);

        return $this->success($hotel, 'Hotel updated successfully');
    }

    public function destroy(Hotel $hotel): JsonResponse
    {
        ActivityLog::record('hotel.deleted', $hotel, ['name' => $hotel->name]);
        $hotel->delete();

        return $this->success(null, 'Hotel deleted successfully');
    }
}
