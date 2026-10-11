<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreTableRequest;
use App\Http\Requests\Admin\UpdateTableRequest;
use App\Models\ActivityLog;
use App\Models\RestaurantTable;
use App\Services\QrCodeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Str;

class AdminTableController extends Controller
{
    public function __construct(private readonly QrCodeService $qrService) {}

    public function index(Request $request): JsonResponse
    {
        $tables = RestaurantTable::query()
            ->when($request->filled('area'), fn ($q) => $q->where('area', $request->area))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->status))
            ->orderBy('area')
            ->orderBy('name')
            ->get()
            ->map(fn ($t) => $this->tableResource($t));

        return $this->success($tables);
    }

    public function store(StoreTableRequest $request): JsonResponse
    {
        $table = RestaurantTable::create($request->validated());
        ActivityLog::record('table.created', $table, ['name' => $table->name]);

        return $this->success($this->tableResource($table), 'Table created', 201);
    }

    public function update(UpdateTableRequest $request, RestaurantTable $table): JsonResponse
    {
        $data = $request->validated();

        // Make unique check exclude current table for name
        if (isset($data['name'])) {
            $exists = RestaurantTable::where('name', $data['name'])
                ->where('id', '!=', $table->id)
                ->exists();

            if ($exists) {
                return $this->error('A table with that name already exists.', 422);
            }
        }

        $table->update($data);
        ActivityLog::record('table.updated', $table, ['name' => $table->name]);

        return $this->success($this->tableResource($table), 'Table updated');
    }

    public function destroy(RestaurantTable $table): JsonResponse
    {
        ActivityLog::record('table.deleted', $table, ['name' => $table->name]);
        $table->delete();

        return $this->success(null, 'Table deleted');
    }

    public function regenerateQr(RestaurantTable $table): JsonResponse
    {
        $table->update(['qr_token' => Str::random(32)]);
        ActivityLog::record('table.qr_regenerated', $table, ['name' => $table->name]);

        return $this->success($this->tableResource($table), 'QR token regenerated');
    }

    public function qrSvg(RestaurantTable $table): Response
    {
        $svg = $this->qrService->svg($this->qrService->tableUrl($table->qr_token), 400);

        return response($svg, 200, ['Content-Type' => 'image/svg+xml']);
    }

    public function menuQrSvg(): Response
    {
        $svg = $this->qrService->svg($this->qrService->menuUrl(), 400);

        return response($svg, 200, ['Content-Type' => 'image/svg+xml']);
    }

    public function areas(): JsonResponse
    {
        $areas = RestaurantTable::distinct()->orderBy('area')->pluck('area')->filter()->values();

        return $this->success($areas);
    }

    private function tableResource(RestaurantTable $table): array
    {
        return [
            'id'          => $table->id,
            'name'        => $table->name,
            'capacity'    => $table->capacity,
            'qr_token'    => $table->qr_token,
            'area'        => $table->area,
            'position_x'  => $table->position_x,
            'position_y'  => $table->position_y,
            'status'      => $table->status instanceof \App\Enums\TableStatus ? $table->status->value : $table->status,
            'qr_url'      => url("/api/v1/admin/tables/{$table->id}/qr"),
            'dine_in_url' => url("/qr/{$table->qr_token}"),
        ];
    }
}
