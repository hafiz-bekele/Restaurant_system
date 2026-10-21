<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AdjustStockRequest;
use App\Http\Requests\Admin\StoreIngredientRequest;
use App\Http\Requests\Admin\StoreSupplierRequest;
use App\Http\Requests\Admin\UpdateIngredientRequest;
use App\Models\ActivityLog;
use App\Models\Ingredient;
use App\Models\StockMovement;
use App\Models\Supplier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminInventoryController extends Controller
{
    public function ingredients(Request $request): JsonResponse
    {
        $items = Ingredient::with('supplier:id,name')
            ->when($request->boolean('low_stock'), fn ($q) => $q->whereRaw('stock <= reorder_level'))
            ->when($request->filled('search'), fn ($q) => $q->where('name', 'like', "%{$request->search}%"))
            ->orderBy('name')
            ->paginate(25);

        return $this->paginated($items->through(fn ($i) => [
            'id'            => $i->id,
            'name'          => $i->name,
            'unit'          => $i->unit,
            'stock'         => $i->stock,
            'reorder_level' => $i->reorder_level,
            'cost'          => $i->cost,
            'is_low_stock'  => $i->isLowStock(),
            'supplier'      => $i->supplier,
        ]));
    }

    public function storeIngredient(StoreIngredientRequest $request): JsonResponse
    {
        $ingredient = Ingredient::create($request->validated());
        ActivityLog::record('ingredient.created', $ingredient);

        return $this->success($ingredient, 'Ingredient created', 201);
    }

    public function updateIngredient(UpdateIngredientRequest $request, Ingredient $ingredient): JsonResponse
    {
        $ingredient->update($request->validated());

        return $this->success($ingredient->fresh(), 'Ingredient updated');
    }

    public function adjustStock(AdjustStockRequest $request, Ingredient $ingredient): JsonResponse
    {
        $data = $request->validated();
        $ingredient->increment('stock', $data['delta']);

        StockMovement::create([
            'ingredient_id' => $ingredient->id,
            'delta'         => $data['delta'],
            'reason'        => $data['reason'],
        ]);

        ActivityLog::record('stock.adjusted', $ingredient, $data);

        return $this->success(['stock' => $ingredient->fresh()->stock], 'Stock adjusted');
    }

    public function lowStock(): JsonResponse
    {
        return $this->success(
            Ingredient::with('supplier:id,name')->whereRaw('stock <= reorder_level')->orderBy('name')->get()
        );
    }

    public function summary(): JsonResponse
    {
        $totalItems = Ingredient::count();
        $lowStockCount = Ingredient::whereRaw('stock <= reorder_level')->count();
        $totalValuation = (float) (Ingredient::selectRaw('SUM(stock * cost) as val')->value('val') ?? 0);
        $suppliersCount = Supplier::count();

        return $this->success([
            'total_items'      => $totalItems,
            'low_stock_count'  => $lowStockCount,
            'total_valuation'  => round($totalValuation, 2),
            'suppliers_count'  => $suppliersCount,
        ]);
    }

    public function destroyIngredient(Ingredient $ingredient): JsonResponse
    {
        ActivityLog::record('ingredient.deleted', $ingredient);
        $ingredient->delete();

        return $this->success(null, 'Ingredient deleted');
    }

    public function suppliers(): JsonResponse
    {
        return $this->success(Supplier::withCount('ingredients')->orderBy('name')->get());
    }

    public function storeSupplier(StoreSupplierRequest $request): JsonResponse
    {
        $supplier = Supplier::create($request->validated());

        return $this->success($supplier, 'Supplier created', 201);
    }

    public function updateSupplier(StoreSupplierRequest $request, Supplier $supplier): JsonResponse
    {
        $supplier->update($request->validated());

        return $this->success($supplier->fresh(), 'Supplier updated');
    }

    public function destroySupplier(Supplier $supplier): JsonResponse
    {
        // Unlink or set supplier_id to null for associated ingredients
        Ingredient::where('supplier_id', $supplier->id)->update(['supplier_id' => null]);
        $supplier->delete();

        return $this->success(null, 'Supplier deleted');
    }

    public function movements(Request $request): JsonResponse
    {
        $movements = StockMovement::with('ingredient:id,name,unit')
            ->orderByDesc('id')
            ->paginate(20);

        return $this->paginated($movements->through(fn ($m) => [
            'id'              => $m->id,
            'ingredient_id'   => $m->ingredient_id,
            'ingredient_name' => $m->ingredient?->name ?? 'Unknown',
            'ingredient_unit' => $m->ingredient?->unit ?? '',
            'delta'           => (float) $m->delta,
            'reason'          => $m->reason,
            'created_at'      => $m->created_at?->toIso8601String() ?? now()->toIso8601String(),
        ]));
    }
}
