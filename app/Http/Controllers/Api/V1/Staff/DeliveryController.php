<?php

namespace App\Http\Controllers\Api\V1\Staff;

use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\V1\OrderResource;
use App\Models\Order;
use App\Services\OrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

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

    public function updateStatus(Request $request, Order $order): JsonResponse
    {
        $request->validate(['status' => 'required|in:out_for_delivery,delivered']);
        $status = OrderStatus::from($request->status);
        $order = $this->orderService->updateStatus($order, $status, $request->user()->id);

        return $this->success(new OrderResource($order));
    }
}
