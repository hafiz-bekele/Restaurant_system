<?php

namespace App\Services;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Coupon;
use App\Models\Dish;
use App\Models\Setting;
use Illuminate\Support\Facades\DB;

class CartService
{
    public function getOrCreate(?int $userId, ?string $sessionId): Cart
    {
        if ($userId) {
            return Cart::firstOrCreate(['user_id' => $userId]);
        }

        return Cart::firstOrCreate(['session_id' => $sessionId]);
    }

    public function addItem(Cart $cart, int $dishId, int $quantity, ?int $variantId = null, array $options = [], ?string $note = null): CartItem
    {
        $dish = Dish::available()->findOrFail($dishId);

        $existing = $cart->items()
            ->where('dish_id', $dishId)
            ->where('variant_id', $variantId)
            ->first();

        if ($existing) {
            $existing->increment('quantity', $quantity);

            return $existing->fresh();
        }

        return $cart->items()->create([
            'dish_id' => $dishId,
            'variant_id' => $variantId,
            'quantity' => $quantity,
            'options' => $options,
            'note' => $note,
        ]);
    }

    public function updateItem(CartItem $item, int $quantity): CartItem
    {
        if ($quantity <= 0) {
            $item->delete();

            return $item;
        }

        $item->update(['quantity' => $quantity]);

        return $item;
    }

    public function removeItem(CartItem $item): void
    {
        $item->delete();
    }

    public function applyCoupon(Cart $cart, string $code): Coupon
    {
        $coupon = Coupon::where('code', strtoupper($code))->first();

        if (! $coupon || ! $coupon->isValid()) {
            throw new \InvalidArgumentException('Invalid or expired coupon code.');
        }

        $cart->update(['coupon_code' => $coupon->code]);

        return $coupon;
    }

    public function removeCoupon(Cart $cart): void
    {
        $cart->update(['coupon_code' => null]);
    }

    public function calculateTotals(Cart $cart, float $deliveryFee = 0): array
    {
        $cart->load(['items.dish', 'items.variant', 'coupon']);

        $subtotal = 0;

        foreach ($cart->items as $item) {
            $price = (float) $item->dish->price;

            if ($item->variant) {
                $price += (float) $item->variant->price_delta;
            }

            // Add addon prices from options
            $addonTotal = collect($item->options ?? [])->sum('price');
            $price += $addonTotal;

            $subtotal += $price * $item->quantity;
        }

        $discount = 0;

        if ($cart->coupon && $cart->coupon->isValid()) {
            $discount = match ($cart->coupon->type->value) {
                'percent' => round($subtotal * ($cart->coupon->value / 100), 2),
                'fixed' => min((float) $cart->coupon->value, $subtotal),
                'free_delivery' => $deliveryFee,
                default => 0,
            };

            if ($cart->coupon->type->value === 'free_delivery') {
                $deliveryFee = 0;
            }
        }

        $taxRate           = (float) Setting::get('tax_rate', 0.15);
        $serviceChargeRate = (float) Setting::get('service_charge_rate', 0.10);

        $taxable = max(0, $subtotal - $discount);

        // REQ-6.2: Taxable Basis = Subtotal + Service Charge Amount
        $serviceCharge = round($taxable * $serviceChargeRate, 2);
        $taxableBasis  = $taxable + $serviceCharge;
        $tax           = round($taxableBasis * $taxRate, 2);
        $total         = $taxableBasis + $tax + $deliveryFee;

        return compact('subtotal', 'discount', 'serviceCharge', 'tax', 'deliveryFee', 'total');
    }

    public function mergeGuestCart(string $sessionId, int $userId): void
    {
        $guestCart = Cart::where('session_id', $sessionId)->with('items')->first();

        if (! $guestCart || $guestCart->items->isEmpty()) {
            return;
        }

        $userCart = $this->getOrCreate($userId, null);

        DB::transaction(function () use ($guestCart, $userCart) {
            foreach ($guestCart->items as $item) {
                $this->addItem($userCart, $item->dish_id, $item->quantity, $item->variant_id, $item->options ?? [], $item->note);
            }

            $guestCart->delete();
        });
    }
}
