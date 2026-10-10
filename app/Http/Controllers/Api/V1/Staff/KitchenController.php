<?php

namespace App\Http\Controllers\Api\V1\Staff;

use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\V1\OrderResource;
use App\Models\Order;
use App\Services\OrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class KitchenController extends Controller
{
    public function __construct(private readonly OrderService $orderService) {}

    public function tickets(): JsonResponse
    {
        $orders = Order::with(['items', 'table'])
            ->whereIn('status', [
                OrderStatus::Confirmed->value,
                OrderStatus::Preparing->value,
            ])
            ->latest()
            ->get();

        return $this->success(OrderResource::collection($orders));
    }

    public function updateTicket(Request $request, Order $order): JsonResponse
    {
        $request->validate(['status' => 'required|in:preparing,ready']);

        $status = OrderStatus::from($request->status);
        $order = $this->orderService->updateStatus($order, $status, $request->user()->id);

        return $this->success(new OrderResource($order), 'Ticket updated');
    }
}
