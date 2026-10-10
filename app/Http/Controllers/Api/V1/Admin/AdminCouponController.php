<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Coupon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class AdminCouponController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $coupons = Coupon::withCount('usages')
            ->when($request->filled('search'), fn ($q) => $q->where('code', 'like', "%{$request->search}%"))
            ->when($request->filled('active'), fn ($q) => $q->where('is_active', (bool) $request->active))
            ->latest()
            ->paginate(20);

        return $this->paginated($coupons);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'code'      => 'nullable|string|max:50|unique:coupons,code',
            'type'      => 'required|in:percent,fixed,free_delivery',
            'value'     => 'required|numeric|min:0',
            'min_order' => 'nullable|numeric|min:0',
            'max_uses'  => 'nullable|integer|min:1',
            'starts_at' => 'nullable|date',
            'ends_at'   => 'nullable|date|after:starts_at',
            'is_active' => 'boolean',
        ]);

        $data['code'] = strtoupper($data['code'] ?? Str::random(8));

        $coupon = Coupon::create($data);
        ActivityLog::record('coupon.created', $coupon, ['code' => $coupon->code]);

        return $this->success($coupon, 'Coupon created', 201);
    }

    public function update(Request $request, Coupon $coupon): JsonResponse
    {
        $data = $request->validate([
            'type'      => 'sometimes|in:percent,fixed,free_delivery',
            'value'     => 'sometimes|numeric|min:0',
            'min_order' => 'nullable|numeric|min:0',
            'max_uses'  => 'nullable|integer|min:1',
            'starts_at' => 'nullable|date',
            'ends_at'   => 'nullable|date',
            'is_active' => 'boolean',
        ]);

        $coupon->update($data);
        ActivityLog::record('coupon.updated', $coupon, ['code' => $coupon->code]);

        return $this->success($coupon->fresh(), 'Coupon updated');
    }

    public function destroy(Coupon $coupon): JsonResponse
    {
        ActivityLog::record('coupon.deleted', null, ['code' => $coupon->code]);
        $coupon->delete();

        return $this->success(null, 'Coupon deleted');
    }
}
