<?php

namespace App\Http\Controllers\Api\V1\Staff;

use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Staff\UpdateKitchenTicketRequest;
use App\Http\Resources\V1\OrderResource;
use App\Models\Order;
use App\Services\OrderService;
use Illuminate\Http\JsonResponse;

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

    public function updateTicket(UpdateKitchenTicketRequest $request, Order $order): JsonResponse
    {
        $data  = $request->validated();
        $order = $this->orderService->updateStatus(
            $order,
            OrderStatus::from($data['status']),
            $request->user()->id,
            $data['note'] ?? null
        );

        return $this->success(new OrderResource($order), 'Ticket updated');
    }
}
