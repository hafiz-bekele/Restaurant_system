<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdminReportController extends Controller
{
    public function sales(Request $request): JsonResponse
    {
        $request->validate([
            'from' => 'required|date',
            'to' => 'required|date|after_or_equal:from',
        ]);

        $data = Order::whereBetween('created_at', [$request->from, $request->to])
            ->where('status', '!=', 'cancelled')
            ->selectRaw('DATE(created_at) as date, COUNT(*) as orders, SUM(total) as revenue, AVG(total) as avg_order_value')
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        $topDishes = OrderItem::whereBetween('created_at', [$request->from, $request->to])
            ->select('name_snapshot', DB::raw('SUM(quantity) as total_qty, SUM(unit_price * quantity) as revenue'))
            ->groupBy('name_snapshot')
            ->orderByDesc('total_qty')
            ->limit(10)
            ->get();

        return $this->success([
            'daily' => $data,
            'top_dishes' => $topDishes,
            'summary' => [
                'total_orders' => $data->sum('orders'),
                'total_revenue' => $data->sum('revenue'),
                'avg_order' => $data->avg('avg_order_value'),
            ],
        ]);
    }
}
