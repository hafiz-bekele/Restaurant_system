<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateOrderStatusRequest;
use App\Http\Resources\V1\OrderResource;
use App\Models\ActivityLog;
use App\Models\Order;
use App\Services\OrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminOrderController extends Controller
{
    public function __construct(private readonly OrderService $orderService) {}

    public function index(Request $request): JsonResponse
    {
        $orders = Order::with(['user:id,name,email', 'items'])
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->status))
            ->when($request->filled('type'), fn ($q) => $q->where('type', $request->type))
            ->latest()
            ->paginate(20);

        return $this->paginated($orders->through(fn ($o) => new OrderResource($o)));
    }

    public function updateStatus(UpdateOrderStatusRequest $request, Order $order): JsonResponse
    {
        $data  = $request->validated();
        $order = $this->orderService->updateStatus(
            $order,
            OrderStatus::from($data['status']),
            $request->user()->id,
            $data['note'] ?? null
        );

        ActivityLog::record('order.status_changed', $order, ['status' => $data['status']]);

        return $this->success(new OrderResource($order), 'Order status updated');
    }
}
