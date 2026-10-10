<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\DeliveryZone;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminDeliveryZoneController extends Controller
{
    public function index(): JsonResponse
    {
        return $this->success(DeliveryZone::orderBy('name')->get());
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name'       => 'required|string|max:100',
            'fee'        => 'required|numeric|min:0',
            'min_order'  => 'nullable|numeric|min:0',
            'eta_minutes'=> 'nullable|integer|min:1',
            'radius_km'  => 'nullable|numeric|min:0',
            'is_active'  => 'boolean',
        ]);

        return $this->success(DeliveryZone::create($data), 'Zone created', 201);
    }

    public function update(Request $request, DeliveryZone $deliveryZone): JsonResponse
    {
        $data = $request->validate([
            'name'        => 'sometimes|string|max:100',
            'fee'         => 'sometimes|numeric|min:0',
            'min_order'   => 'nullable|numeric|min:0',
            'eta_minutes' => 'nullable|integer|min:1',
            'radius_km'   => 'nullable|numeric|min:0',
            'is_active'   => 'boolean',
        ]);

        $deliveryZone->update($data);

        return $this->success($deliveryZone->fresh(), 'Zone updated');
    }

    public function destroy(DeliveryZone $deliveryZone): JsonResponse
    {
        $deliveryZone->delete();

        return $this->success(null, 'Zone deleted');
    }
}
