<?php

namespace App\Http\Controllers\Api\V1\Order;

use App\Http\Controllers\Controller;
use App\Http\Resources\V1\OrderResource;
use App\Models\Order;
use App\Services\CartService;
use App\Services\OrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class OrderController extends Controller
{
    public function __construct(
        private readonly OrderService $orderService,
        private readonly CartService $cartService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $orders = Order::with(['items'])
            ->where('user_id', $request->user()->id)
            ->latest()
            ->paginate(15);

        return $this->paginated($orders->through(fn ($o) => new OrderResource($o)));
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'type' => 'required|in:delivery,pickup,dine_in',
            'payment_method' => 'required|in:stripe,chapa,cash',
            'table_id' => 'required_if:type,dine_in|nullable|integer|exists:tables,id',
            'delivery_zone_id' => 'required_if:type,delivery|nullable|integer|exists:delivery_zones,id',
            'address' => 'required_if:type,delivery|nullable|array',
            'tip' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string|max:500',
            'scheduled_at' => 'nullable|date|after:now',
        ]);

        $cart = $this->cartService->getOrCreate(
            $request->user()?->id,
            $request->header('X-Session-Id')
        );

        $cart->load('items');

        if ($cart->items->isEmpty()) {
            return $this->error('Your cart is empty.', 422);
        }

        $idempotencyKey = $request->header('X-Idempotency-Key', (string) Str::uuid());
        $order = $this->orderService->createFromCart($cart, $data, $idempotencyKey);

        return $this->success(new OrderResource($order->load('items', 'statusHistory')), 'Order placed successfully', 201);
    }

    public function show(Order $order): JsonResponse
    {
        $this->authorize('view', $order);

        return $this->success(new OrderResource($order->load('items', 'statusHistory', 'payment')));
    }

    public function cancel(Request $request, Order $order): JsonResponse
    {
        $this->authorize('cancel', $order);
        $data = $request->validate(['reason' => 'nullable|string|max:255']);
        $order = $this->orderService->cancel($order, $data['reason'] ?? null);

        return $this->success(new OrderResource($order), 'Order cancelled');
    }

    public function track(Order $order): JsonResponse
    {
        return $this->success([
            'ulid' => $order->ulid,
            'status' => $order->status->value,
            'status_label' => $order->status->label(),
            'status_history' => $order->statusHistory()->latest()->get(),
            'estimated_time' => $order->deliveryZone?->eta_minutes,
        ]);
    }
}
