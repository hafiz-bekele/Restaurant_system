<?php

namespace App\Http\Controllers\Api\V1\Staff;

use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Staff\UpdateDeliveryStatusRequest;
use App\Http\Resources\V1\OrderResource;
use App\Models\Order;
use App\Services\OrderService;
use Illuminate\Http\JsonResponse;

class DeliveryController extends Controller
{
    public function __construct(private readonly OrderService $orderService) {}

    public function index(): JsonResponse
    {
        $orders = Order::with(['items', 'deliveryZone'])
            ->whereIn('status', [OrderStatus::OutForDelivery->value, OrderStatus::Ready->value])
            ->where('type', 'delivery')
            ->latest()
            ->get();

        return $this->success(OrderResource::collection($orders));
    }

    public function updateStatus(UpdateDeliveryStatusRequest $request, Order $order): JsonResponse
    {
        $data  = $request->validated();
        $order = $this->orderService->updateStatus(
            $order,
            OrderStatus::from($data['status']),
            $request->user()->id,
            $data['note'] ?? null
        );

        return $this->success(new OrderResource($order), 'Delivery status updated');
    }
}
