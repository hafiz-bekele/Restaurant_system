<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreCouponRequest;
use App\Http\Requests\Admin\UpdateCouponRequest;
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

    public function store(StoreCouponRequest $request): JsonResponse
    {
        $data         = $request->validated();
        $data['code'] = strtoupper($data['code'] ?? Str::random(8));

        $coupon = Coupon::create($data);
        ActivityLog::record('coupon.created', $coupon, ['code' => $coupon->code]);

        return $this->success($coupon, 'Coupon created', 201);
    }

    public function update(UpdateCouponRequest $request, Coupon $coupon): JsonResponse
    {
        $coupon->update($request->validated());
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
