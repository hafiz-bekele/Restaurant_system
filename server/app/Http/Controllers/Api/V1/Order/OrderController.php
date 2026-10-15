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

        $user = $request->user() ?? auth('sanctum')->user();

        $cart = $this->cartService->getOrCreate(
            $user?->id,
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

    public function show(Order $order, Request $request): JsonResponse
    {
        $user = $request->user() ?? auth('sanctum')->user();
        if ($order->user_id && (! $user || ($user->id !== $order->user_id && ! $user->hasAnyRole(['admin', 'super-admin', 'manager', 'kitchen', 'waiter', 'cashier', 'delivery', 'reception'])))) {
            $this->authorize('view', $order);
        }

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
            'id'               => $order->id,
            'ulid'             => $order->ulid,
            'order_number'     => $order->order_number ?? ('#' . substr($order->ulid, -8)),
            'type'             => $order->type?->value ?? 'dine_in',
            'status'           => $order->status->value,
            'status_label'     => $order->status->label(),
            'status_history'   => $order->statusHistory()->latest()->get(),
            'estimated_time'   => $order->deliveryZone?->eta_minutes,
            'subtotal'         => (float) $order->subtotal,
            'tax'              => (float) $order->tax,
            'service_charge'   => (float) $order->service_charge,
            'delivery_fee'     => (float) $order->delivery_fee,
            'discount'         => (float) $order->discount,
            'total'            => (float) $order->total,
            'payment_method'   => $order->payment_method,
            'payment_status'   => $order->payment_status?->value ?? 'pending',
            'address_snapshot' => $order->address_snapshot,
            'notes'            => $order->notes,
            'created_at'       => $order->created_at?->toIso8601String(),
            'items'            => $order->items()->with('dish:id,name,image,slug')->get(),
        ]);
    }
}
