<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SalesReportRequest;
use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class AdminReportController extends Controller
{
    public function sales(SalesReportRequest $request): JsonResponse
    {
        $data = $request->validated();

        $daily = Order::whereBetween('created_at', [$data['from'], $data['to']])
            ->where('status', '!=', 'cancelled')
            ->selectRaw('DATE(created_at) as date, COUNT(*) as orders, SUM(total) as revenue, AVG(total) as avg_order_value')
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        $topDishes = OrderItem::whereBetween('created_at', [$data['from'], $data['to']])
            ->select('name_snapshot', DB::raw('SUM(quantity) as total_qty, SUM(unit_price * quantity) as revenue'))
            ->groupBy('name_snapshot')
            ->orderByDesc('total_qty')
            ->limit(10)
            ->get();

        return $this->success([
            'daily'      => $daily,
            'top_dishes' => $topDishes,
            'summary'    => [
                'total_orders'  => $daily->sum('orders'),
                'total_revenue' => $daily->sum('revenue'),
                'avg_order'     => $daily->avg('avg_order_value'),
            ],
        ]);
    }
}
