<?php

namespace App\Http\Controllers\Api\V1\Staff;

use App\Enums\OrderStatus;
use App\Enums\OrderType;
use App\Enums\PaymentStatus;
use App\Enums\TableStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\V1\OrderResource;
use App\Models\ActivityLog;
use App\Models\CashDrawerTransaction;
use App\Models\CashierShift;
use App\Models\Category;
use App\Models\Dish;
use App\Models\DishVariant;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\RestaurantTable;
use App\Models\Setting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Uid\Ulid;

class CashierController extends Controller
{
    /**
     * Retrieve orders list with comprehensive filtering and search.
     */
    public function orders(Request $request): JsonResponse
    {
        $query = Order::with(['items.dish.category', 'table', 'user', 'payment', 'activeWaiter.waiter'])
            ->latest();

        // Status / payment filter
        $filter = $request->query('filter', 'all');
        if ($filter === 'pending') {
            $query->where('payment_status', PaymentStatus::Pending);
        } elseif ($filter === 'paid') {
            $query->where('payment_status', PaymentStatus::Paid);
        } elseif ($filter === 'refunded') {
            $query->where('payment_status', PaymentStatus::Refunded);
        } elseif ($filter === 'dine_in') {
            $query->where('type', OrderType::DineIn);
        } elseif ($filter === 'pickup') {
            $query->where('type', OrderType::Pickup);
        } elseif ($filter === 'delivery') {
            $query->where('type', OrderType::Delivery);
        }

        // Text search across ulid, customer name, notes, or table name
        if ($search = $request->query('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('ulid', 'like', "%{$search}%")
                    ->orWhere('notes', 'like', "%{$search}%")
                    ->orWhereHas('user', fn ($u) => $u->where('name', 'like', "%{$search}%")->orWhere('phone', 'like', "%{$search}%"))
                    ->orWhereHas('table', fn ($t) => $t->where('name', 'like', "%{$search}%"));
            });
        }

        // Optional date filter (defaults to today if explicitly requested or returns latest 100)
        if ($date = $request->query('date')) {
            $query->whereDate('created_at', $date);
        }

        $orders = $query->limit(100)->get();

        return $this->success(OrderResource::collection($orders));
    }

    /**
     * Get active shift for current cashier user.
     */
    public function currentShift(Request $request): JsonResponse
    {
        $shift = CashierShift::with(['user', 'drawerTransactions' => fn ($q) => $q->latest()])
            ->where('user_id', $request->user()->id)
            ->where('status', 'open')
            ->latest()
            ->first();

        if (! $shift) {
            return $this->success([
                'has_active_shift' => false,
                'shift'            => null,
            ]);
        }

        $payIns = (float) $shift->drawerTransactions()->where('type', 'pay_in')->sum('amount');
        $payOuts = (float) $shift->drawerTransactions()->whereIn('type', ['pay_out', 'drop'])->sum('amount');
        $expectedCash = (float) $shift->opening_float + (float) $shift->cash_sales + $payIns - $payOuts;

        return $this->success([
            'has_active_shift' => true,
            'shift'            => $shift,
            'summary'          => [
                'opening_float' => (float) $shift->opening_float,
                'cash_sales'    => (float) $shift->cash_sales,
                'digital_sales' => (float) $shift->digital_sales,
                'room_charge'   => (float) $shift->room_charge_sales,
                'total_sales'   => (float) $shift->total_sales,
                'pay_ins'       => $payIns,
                'pay_outs'      => $payOuts,
                'expected_cash' => $expectedCash,
                'orders_count'  => $shift->orders_count,
                'opened_at'     => $shift->opened_at,
            ],
        ]);
    }

    /**
     * Open a new shift for the current cashier.
     */
    public function openShift(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'opening_float' => ['required', 'numeric', 'min:0'],
            'opening_notes' => ['nullable', 'string', 'max:500'],
        ]);

        $existing = CashierShift::where('user_id', $request->user()->id)
            ->where('status', 'open')
            ->first();

        if ($existing) {
            return $this->error('An active open shift is already in progress.', 422);
        }

        $shift = CashierShift::create([
            'user_id'       => $request->user()->id,
            'hotel_id'      => $request->user()->hotel_id ?? 1,
            'opening_float' => $validated['opening_float'],
            'opening_notes' => $validated['opening_notes'] ?? null,
            'opened_at'     => now(),
            'status'        => 'open',
        ]);

        ActivityLog::record('cashier.shift_opened', $shift, [
            'opening_float' => $shift->opening_float,
            'cashier'       => $request->user()->name,
        ]);

        return $this->success($shift->load('user'), 'Shift opened successfully');
    }

    /**
     * Close the current shift (generate Z-Report).
     */
    public function closeShift(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'closing_cash_counted' => ['required', 'numeric', 'min:0'],
            'closing_notes'        => ['nullable', 'string', 'max:1000'],
        ]);

        $shift = CashierShift::with('drawerTransactions')
            ->where('user_id', $request->user()->id)
            ->where('status', 'open')
            ->first();

        if (! $shift) {
            return $this->error('No active open shift found to close.', 404);
        }

        $payIns = (float) $shift->drawerTransactions()->where('type', 'pay_in')->sum('amount');
        $payOuts = (float) $shift->drawerTransactions()->whereIn('type', ['pay_out', 'drop'])->sum('amount');
        $expectedCash = (float) $shift->opening_float + (float) $shift->cash_sales + $payIns - $payOuts;
        $variance = (float) $validated['closing_cash_counted'] - $expectedCash;

        $shift->update([
            'closing_cash_counted' => $validated['closing_cash_counted'],
            'expected_cash'        => $expectedCash,
            'variance'             => $variance,
            'closing_notes'        => $validated['closing_notes'] ?? null,
            'status'               => 'closed',
            'closed_at'            => now(),
        ]);

        ActivityLog::record('cashier.shift_closed', $shift, [
            'counted_cash'  => $validated['closing_cash_counted'],
            'expected_cash' => $expectedCash,
            'variance'      => $variance,
            'total_sales'   => $shift->total_sales,
        ]);

        return $this->success([
            'shift'    => $shift->fresh(['user', 'drawerTransactions']),
            'z_report' => [
                'shift_id'             => $shift->id,
                'cashier_name'         => $request->user()->name,
                'opened_at'            => $shift->opened_at,
                'closed_at'            => $shift->closed_at,
                'opening_float'        => (float) $shift->opening_float,
                'cash_sales'           => (float) $shift->cash_sales,
                'digital_sales'        => (float) $shift->digital_sales,
                'room_charge_sales'    => (float) $shift->room_charge_sales,
                'total_sales'          => (float) $shift->total_sales,
                'pay_ins'              => $payIns,
                'pay_outs'             => $payOuts,
                'expected_cash'        => $expectedCash,
                'closing_cash_counted' => (float) $shift->closing_cash_counted,
                'variance'             => $variance,
                'orders_count'         => $shift->orders_count,
                'closing_notes'        => $shift->closing_notes,
            ],
        ], 'Shift closed successfully. Z-Report generated.');
    }

    /**
     * Record cash drawer transaction (Pay-In, Pay-Out, or Drop).
     */
    public function drawerAction(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'type'   => ['required', 'in:pay_in,pay_out,drop'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'reason' => ['required', 'string', 'max:255'],
        ]);

        $shift = CashierShift::where('user_id', $request->user()->id)
            ->where('status', 'open')
            ->first();

        if (! $shift) {
            return $this->error('You must have an active open shift to record drawer transactions.', 422);
        }

        $tx = $shift->drawerTransactions()->create([
            'user_id' => $request->user()->id,
            'type'    => $validated['type'],
            'amount'  => $validated['amount'],
            'reason'  => $validated['reason'],
        ]);

        ActivityLog::record("cashier.drawer_{$validated['type']}", $tx, [
            'amount' => $validated['amount'],
            'reason' => $validated['reason'],
        ]);

        return $this->success($tx, 'Drawer transaction recorded successfully');
    }

    /**
     * View past cashier shifts history.
     */
    public function shiftHistory(): JsonResponse
    {
        $shifts = CashierShift::with(['user', 'drawerTransactions'])
            ->latest('closed_at')
            ->limit(30)
            ->get();

        return $this->success($shifts);
    }

    /**
     * Fast searchable catalog of available dishes and categories for POS register.
     */
    public function menu(): JsonResponse
    {
        $categories = Category::with(['dishes' => function ($q) {
            $q->where('is_available', true)->with('variants');
        }])
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get();

        return $this->success($categories);
    }

    /**
     * Direct counter POS Order creation by cashier.
     */
    public function createPosOrder(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'type'            => ['required', 'in:dine_in,pickup,delivery'],
            'table_id'        => ['nullable', 'exists:tables,id'],
            'customer_name'   => ['nullable', 'string', 'max:100'],
            'customer_phone'  => ['nullable', 'string', 'max:30'],
            'notes'           => ['nullable', 'string', 'max:500'],
            'items'           => ['required', 'array', 'min:1'],
            'items.*.dish_id' => ['required', 'exists:dishes,id'],
            'items.*.quantity'=> ['required', 'integer', 'min:1'],
            'items.*.variant_id'=> ['nullable', 'exists:dish_variants,id'],
            'items.*.options' => ['nullable', 'array'],
            'discount'        => ['nullable', 'numeric', 'min:0'],
            'payment_type'    => ['nullable', 'string'],
            'amount_tendered' => ['nullable', 'numeric'],
            'auto_settle'     => ['nullable', 'boolean'],
        ]);

        return DB::transaction(function () use ($validated, $request) {
            $subtotal = 0;
            $itemsData = [];

            foreach ($validated['items'] as $item) {
                $dish = Dish::with('category')->findOrFail($item['dish_id']);
                $unitPrice = (float) $dish->price;

                if (! empty($item['variant_id'])) {
                    $variant = DishVariant::find($item['variant_id']);
                    if ($variant) {
                        $unitPrice = (float) $variant->price;
                    }
                }

                $qty = (int) $item['quantity'];
                $subtotal += $unitPrice * $qty;

                $itemsData[] = [
                    'dish_id'       => $dish->id,
                    'name_snapshot' => $dish->name,
                    'unit_price'    => $unitPrice,
                    'quantity'      => $qty,
                    'options'       => $item['options'] ?? null,
                    'station'       => $dish->category?->name ?? 'kitchen',
                ];
            }

            // Rates from system settings
            $taxRate = (float) Setting::get('tax_rate', 0.15);
            $serviceChargeRate = $validated['type'] === 'dine_in'
                ? (float) Setting::get('service_charge_rate', 0.10)
                : 0.00;

            $tax = round($subtotal * $taxRate, 2);
            $serviceCharge = round($subtotal * $serviceChargeRate, 2);
            $discount = min((float) ($validated['discount'] ?? 0), $subtotal);
            $total = max(0, round($subtotal + $tax + $serviceCharge - $discount, 2));

            $order = Order::create([
                'ulid'           => (string) new Ulid(),
                'user_id'        => null,
                'table_id'       => $validated['table_id'] ?? null,
                'type'           => $validated['type'],
                'status'         => OrderStatus::Confirmed,
                'subtotal'       => $subtotal,
                'tax'            => $tax,
                'service_charge' => $serviceCharge,
                'delivery_fee'   => 0,
                'discount'       => $discount,
                'total'          => $total,
                'payment_status' => PaymentStatus::Pending,
                'payment_method' => $validated['payment_type'] ?? 'cash',
                'notes'          => $validated['notes'] ?? ($validated['customer_name'] ? "Guest: {$validated['customer_name']}" : null),
            ]);

            foreach ($itemsData as $data) {
                $order->items()->create($data);
            }

            if (! empty($validated['table_id']) && $validated['type'] === 'dine_in') {
                RestaurantTable::where('id', $validated['table_id'])->update(['status' => TableStatus::Occupied]);
            }

            // If auto settle requested upon order creation
            if (! empty($validated['auto_settle'])) {
                $paymentMethod = $validated['payment_type'] ?? 'cash';
                $tendered = (float) ($validated['amount_tendered'] ?? $total);
                $change = max(0, $tendered - $total);

                $order->update([
                    'payment_status' => PaymentStatus::Paid,
                    'payment_method' => $paymentMethod,
                ]);

                Payment::create([
                    'order_id'       => $order->id,
                    'gateway'        => $paymentMethod,
                    'amount'         => $total,
                    'currency'       => 'ETB',
                    'status'         => PaymentStatus::Paid,
                    'transaction_id' => 'POS-' . strtoupper(bin2hex(random_bytes(4))),
                    'payload'        => [
                        'tendered'     => $tendered,
                        'change'       => $change,
                        'cashier_id'   => $request->user()->id,
                        'cashier_name' => $request->user()->name,
                    ],
                ]);

                // Update active shift sales tally
                $this->accumulateShiftSales($request->user()->id, $paymentMethod, $total);
            }

            ActivityLog::record('cashier.pos_order_created', $order, [
                'total'   => $total,
                'cashier' => $request->user()->name,
            ]);

            return $this->success(
                new OrderResource($order->fresh(['items.dish', 'table', 'user', 'payment'])),
                'POS Order created successfully'
            );
        });
    }

    /**
     * Settle payment for an order with multiple payment channels & tendered calculation.
     */
    public function confirmPayment(Request $request, Order $order): JsonResponse
    {
        $validated = $request->validate([
            'payment_type'    => ['required', 'string'],
            'amount_tendered' => ['nullable', 'numeric'],
            'change_due'      => ['nullable', 'numeric'],
            'transaction_id'  => ['nullable', 'string', 'max:100'],
            'room_number'     => ['nullable', 'string', 'max:50'],
            'split_details'   => ['nullable', 'array'],
            'notes'           => ['nullable', 'string', 'max:255'],
        ]);

        $paymentMethod = $validated['payment_type'];
        $amount = (float) $order->total;
        $tendered = (float) ($validated['amount_tendered'] ?? $amount);
        $change = max(0, $tendered - $amount);
        $txnId = $validated['transaction_id'] ?? ('PAY-' . strtoupper(bin2hex(random_bytes(4))));

        $order->update([
            'payment_status' => PaymentStatus::Paid,
            'payment_method' => $paymentMethod,
        ]);

        Payment::create([
            'order_id'       => $order->id,
            'gateway'        => $paymentMethod,
            'amount'         => $amount,
            'currency'       => 'ETB',
            'status'         => PaymentStatus::Paid,
            'transaction_id' => $txnId,
            'payload'        => [
                'tendered'      => $tendered,
                'change'        => $change,
                'room_number'   => $validated['room_number'] ?? null,
                'split_details' => $validated['split_details'] ?? null,
                'notes'         => $validated['notes'] ?? null,
                'cashier_id'    => $request->user()->id,
                'cashier_name'  => $request->user()->name,
                'settled_at'    => now()->toIso8601String(),
            ],
        ]);

        // Accumulate sales into cashier's active shift
        $this->accumulateShiftSales($request->user()->id, $paymentMethod, $amount);

        ActivityLog::record('cashier.payment_confirmed', $order, [
            'method'  => $paymentMethod,
            'amount'  => $amount,
            'cashier' => $request->user()->name,
        ]);

        return $this->success(
            new OrderResource($order->fresh(['items.dish', 'table', 'user', 'payment'])),
            'Payment confirmed and receipt generated successfully'
        );
    }

    /**
     * Calculate split bill shares (equal split preview).
     */
    public function splitBill(Request $request, Order $order): JsonResponse
    {
        $ways = max(2, min(20, (int) $request->query('ways', 2)));
        $total = (float) $order->total;
        $perPerson = round($total / $ways, 2);

        return $this->success([
            'order_ulid'    => $order->ulid,
            'grand_total'   => $total,
            'split_ways'    => $ways,
            'per_person'    => $perPerson,
            'currency'      => 'ETB',
            'items_count'   => $order->items()->count(),
        ]);
    }

    /**
     * Void / cancel an unpaid order.
     */
    public function voidOrder(Request $request, Order $order): JsonResponse
    {
        $validated = $request->validate([
            'reason' => ['required', 'string', 'max:255'],
        ]);

        $order->update([
            'status' => OrderStatus::Cancelled,
            'notes'  => trim(($order->notes ?? '') . " [VOIDED: {$validated['reason']}]"),
        ]);

        if ($order->table_id && $order->type === OrderType::DineIn) {
            RestaurantTable::where('id', $order->table_id)->update(['status' => TableStatus::Available]);
        }

        ActivityLog::record('cashier.order_voided', $order, [
            'reason'  => $validated['reason'],
            'cashier' => $request->user()->name,
        ]);

        return $this->success(
            new OrderResource($order->fresh(['items.dish', 'table', 'user', 'payment'])),
            'Order voided successfully'
        );
    }

    /**
     * Refund a paid order.
     */
    public function refundOrder(Request $request, Order $order): JsonResponse
    {
        $validated = $request->validate([
            'reason' => ['required', 'string', 'max:255'],
        ]);

        if ($order->payment_status !== PaymentStatus::Paid) {
            return $this->error('Only paid orders can be refunded.', 422);
        }

        $order->update([
            'status'         => OrderStatus::Refunded,
            'payment_status' => PaymentStatus::Refunded,
            'notes'          => trim(($order->notes ?? '') . " [REFUNDED: {$validated['reason']}]"),
        ]);

        Payment::create([
            'order_id'       => $order->id,
            'gateway'        => $order->payment_method ?? 'cash',
            'amount'         => -$order->total,
            'currency'       => 'ETB',
            'status'         => PaymentStatus::Refunded,
            'transaction_id' => 'REF-' . strtoupper(bin2hex(random_bytes(4))),
            'payload'        => [
                'reason'       => $validated['reason'],
                'cashier_id'   => $request->user()->id,
                'cashier_name' => $request->user()->name,
            ],
        ]);

        ActivityLog::record('cashier.order_refunded', $order, [
            'reason'  => $validated['reason'],
            'amount'  => $order->total,
            'cashier' => $request->user()->name,
        ]);

        return $this->success(
            new OrderResource($order->fresh(['items.dish', 'table', 'user', 'payment'])),
            'Order refunded successfully'
        );
    }

    /**
     * Today's cashier financial summary & active shift status.
     */
    public function summary(Request $request): JsonResponse
    {
        $today = now()->startOfDay();

        $cashTotal = (float) Order::where('created_at', '>=', $today)
            ->where('payment_status', PaymentStatus::Paid)
            ->where('payment_method', 'cash')
            ->sum('total');

        $telebirrTotal = (float) Order::where('created_at', '>=', $today)
            ->where('payment_status', PaymentStatus::Paid)
            ->where('payment_method', 'telebirr')
            ->sum('total');

        $cbeTotal = (float) Order::where('created_at', '>=', $today)
            ->where('payment_status', PaymentStatus::Paid)
            ->where('payment_method', 'cbe_birr')
            ->sum('total');

        $cardTotal = (float) Order::where('created_at', '>=', $today)
            ->where('payment_status', PaymentStatus::Paid)
            ->whereIn('payment_method', ['card', 'stripe'])
            ->sum('total');

        $roomChargeTotal = (float) Order::where('created_at', '>=', $today)
            ->where('payment_status', PaymentStatus::Paid)
            ->where('payment_method', 'room_charge')
            ->sum('total');

        $digitalTotal = $telebirrTotal + $cbeTotal + $cardTotal + (float) Order::where('created_at', '>=', $today)
            ->where('payment_status', PaymentStatus::Paid)
            ->where('payment_method', 'chapa')
            ->sum('total');

        $pendingCount = Order::where('created_at', '>=', $today)
            ->where('payment_status', PaymentStatus::Pending)
            ->count();

        $pendingTotal = (float) Order::where('created_at', '>=', $today)
            ->where('payment_status', PaymentStatus::Pending)
            ->sum('total');

        $ordersCount = Order::where('created_at', '>=', $today)->count();
        $grandTotal = $cashTotal + $digitalTotal + $roomChargeTotal;

        // Active shift snapshot
        $activeShift = CashierShift::where('user_id', $request->user()->id)
            ->where('status', 'open')
            ->first();

        return $this->success([
            'date'              => $today->toDateString(),
            'currency'          => 'ETB',
            'orders_count'      => $ordersCount,
            'pending_count'     => $pendingCount,
            'pending_total'     => $pendingTotal,
            'cash_total'        => $cashTotal,
            'telebirr_total'    => $telebirrTotal,
            'cbe_total'         => $cbeTotal,
            'card_total'        => $cardTotal,
            'digital_total'     => $digitalTotal,
            'room_charge_total' => $roomChargeTotal,
            'grand_total'       => $grandTotal,
            'has_active_shift'  => (bool) $activeShift,
            'active_shift_id'   => $activeShift?->id,
            'active_shift_float'=> (float) ($activeShift?->opening_float ?? 0),
        ]);
    }

    /**
     * Helper to accumulate settled order totals into cashier's active shift.
     */
    private function accumulateShiftSales(int $userId, string $method, float $amount): void
    {
        $shift = CashierShift::where('user_id', $userId)
            ->where('status', 'open')
            ->first();

        if (! $shift) {
            return;
        }

        $shift->orders_count += 1;
        $shift->total_sales += $amount;

        if ($method === 'cash') {
            $shift->cash_sales += $amount;
        } elseif ($method === 'room_charge') {
            $shift->room_charge_sales += $amount;
        } else {
            $shift->digital_sales += $amount;
        }

        $shift->save();
    }
}
