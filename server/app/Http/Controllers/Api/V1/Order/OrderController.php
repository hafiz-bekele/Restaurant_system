<?php

namespace App\Http\Controllers\Api\V1\Order;

use App\Http\Controllers\Controller;
use App\Http\Requests\Order\CancelOrderRequest;
use App\Http\Requests\Order\StoreOrderRequest;
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

    public function store(StoreOrderRequest $request): JsonResponse
    {
        $data = $request->validated();

        $cart = $this->cartService->getOrCreate(
            $request->user()?->id,
            $request->header('X-Session-Id')
        );

        $cart->load('items');

        if ($cart->items->isEmpty()) {
            return $this->error('Your cart is empty.', 422);
        }

        $idempotencyKey = $request->header('X-Idempotency-Key', (string) Str::uuid());
        $order          = $this->orderService->createFromCart($cart, $data, $idempotencyKey);

        return $this->success(
            new OrderResource($order->load('items', 'statusHistory')),
            'Order placed successfully',
            201
        );
    }

    public function show(Order $order): JsonResponse
    {
        $this->authorize('view', $order);

        return $this->success(new OrderResource($order->load('items', 'statusHistory', 'payment')));
    }

    public function cancel(CancelOrderRequest $request, Order $order): JsonResponse
    {
        $this->authorize('cancel', $order);

        $order = $this->orderService->cancel($order, $request->validated()['reason'] ?? null);

        return $this->success(new OrderResource($order), 'Order cancelled');
    }

    public function track(Order $order): JsonResponse
    {
        return $this->success([
            'id'             => $order->id,
            'ulid'           => $order->ulid,
            'order_number'   => $order->order_number,
            'status'         => $order->status->value,
            'status_label'   => $order->status->label(),
            'status_history' => $order->statusHistory()->latest()->get(),
            'estimated_time' => $order->deliveryZone?->eta_minutes,
            'total'          => (float) $order->total,
            'payment_method' => $order->payment_method,
            'payment_status' => $order->payment_status?->value ?? 'pending',
            'items'          => $order->items()->with('dish:id,name,image,slug')->get(),
        ]);
    }
}
