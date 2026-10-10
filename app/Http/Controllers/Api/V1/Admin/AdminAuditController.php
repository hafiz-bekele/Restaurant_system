<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminAuditController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $logs = ActivityLog::with('causer:id,name,email')
            ->when($request->filled('action'), fn ($q) => $q->where('action', 'like', "%{$request->action}%"))
            ->when($request->filled('user_id'), fn ($q) => $q->where('causer_id', $request->user_id))
            ->orderByDesc('created_at')
            ->paginate(30);

        return $this->paginated($logs);
    }
}
