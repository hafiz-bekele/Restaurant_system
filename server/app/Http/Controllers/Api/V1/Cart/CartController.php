<?php

namespace App\Http\Controllers\Api\V1\Cart;

use App\Http\Controllers\Controller;
use App\Http\Requests\Cart\AddCartItemRequest;
use App\Http\Requests\Cart\ApplyCouponRequest;
use App\Http\Requests\Cart\UpdateCartItemRequest;
use App\Http\Resources\V1\CartResource;
use App\Models\CartItem;
use App\Services\CartService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CartController extends Controller
{
    public function __construct(private readonly CartService $cartService) {}

    private function resolveCart(Request $request)
    {
        return $this->cartService->getOrCreate(
            $request->user()?->id,
            $request->header('X-Session-Id')
        );
    }

    public function show(Request $request): JsonResponse
    {
        $cart   = $this->resolveCart($request)->load(['items.dish.images', 'items.variant', 'coupon']);
        $totals = $this->cartService->calculateTotals($cart);

        return $this->success([
            'cart'   => new CartResource($cart),
            'totals' => $totals,
        ]);
    }

    public function addItem(AddCartItemRequest $request): JsonResponse
    {
        $data = $request->validated();
        $cart = $this->resolveCart($request);

        $item = $this->cartService->addItem(
            $cart,
            $data['dish_id'],
            $data['quantity'],
            $data['variant_id'] ?? null,
            $data['options']    ?? [],
            $data['note']       ?? null
        );

        return $this->success($item->load('dish', 'variant'), 'Item added to cart', 201);
    }

    public function updateItem(UpdateCartItemRequest $request, CartItem $cartItem): JsonResponse
    {
        $item = $this->cartService->updateItem($cartItem, $request->validated()['quantity']);

        return $this->success($item, 'Cart item updated');
    }

    public function removeItem(CartItem $cartItem): JsonResponse
    {
        $this->cartService->removeItem($cartItem);

        return $this->success(null, 'Item removed from cart');
    }

    public function applyCoupon(ApplyCouponRequest $request): JsonResponse
    {
        $cart   = $this->resolveCart($request);
        $coupon = $this->cartService->applyCoupon($cart, $request->validated()['code']);

        return $this->success(['coupon' => $coupon], 'Coupon applied');
    }

    public function removeCoupon(Request $request): JsonResponse
    {
        $this->cartService->removeCoupon($this->resolveCart($request));

        return $this->success(null, 'Coupon removed');
    }
}
