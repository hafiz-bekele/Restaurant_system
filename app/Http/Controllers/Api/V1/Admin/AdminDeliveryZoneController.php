<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreDeliveryZoneRequest;
use App\Http\Requests\Admin\UpdateDeliveryZoneRequest;
use App\Models\DeliveryZone;
use Illuminate\Http\JsonResponse;

class AdminDeliveryZoneController extends Controller
{
    public function index(): JsonResponse
    {
        return $this->success(DeliveryZone::orderBy('name')->get());
    }

    public function store(StoreDeliveryZoneRequest $request): JsonResponse
    {
        return $this->success(DeliveryZone::create($request->validated()), 'Zone created', 201);
    }

    public function update(UpdateDeliveryZoneRequest $request, DeliveryZone $deliveryZone): JsonResponse
    {
        $deliveryZone->update($request->validated());

        return $this->success($deliveryZone->fresh(), 'Zone updated');
    }

    public function destroy(DeliveryZone $deliveryZone): JsonResponse
    {
        $deliveryZone->delete();

        return $this->success(null, 'Zone deleted');
    }
}
