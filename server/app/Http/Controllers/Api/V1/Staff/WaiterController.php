<?php

namespace App\Http\Controllers\Api\V1\Staff;

use App\Http\Controllers\Controller;
use App\Models\RestaurantTable;
use Illuminate\Http\JsonResponse;

class WaiterController extends Controller
{
    public function tables(): JsonResponse
    {
        $tables = RestaurantTable::withCount(['orders' => fn ($q) => $q->whereNotIn('status', ['delivered', 'cancelled'])])->get();

        return $this->success($tables);
    }
}
