<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Ingredient;
use App\Models\StockMovement;
use App\Models\Supplier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminInventoryController extends Controller
{
    // ── Ingredients ─────────────────────────────────────────────────────────

    public function ingredients(Request $request): JsonResponse
    {
        $items = Ingredient::with('supplier:id,name')
            ->when($request->boolean('low_stock'), fn ($q) => $q->whereRaw('stock <= reorder_level'))
            ->when($request->filled('search'), fn ($q) => $q->where('name', 'like', "%{$request->search}%"))
            ->orderBy('name')
            ->paginate(25);

        return $this->paginated($items->through(fn ($i) => [
            'id'             => $i->id,
            'name'           => $i->name,
            'unit'           => $i->unit,
            'stock'          => $i->stock,
            'reorder_level'  => $i->reorder_level,
            'cost'           => $i->cost,
            'is_low_stock'   => $i->isLowStock(),
            'supplier'       => $i->supplier,
        ]));
    }

    public function storeIngredient(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name'          => 'required|string|max:255',
            'unit'          => 'required|string|max:20',
            'stock'         => 'required|numeric|min:0',
            'reorder_level' => 'required|numeric|min:0',
            'cost'          => 'required|numeric|min:0',
            'supplier_id'   => 'nullable|integer|exists:suppliers,id',
        ]);

        $ingredient = Ingredient::create($data);
        ActivityLog::record('ingredient.created', $ingredient);

        return $this->success($ingredient, 'Ingredient created', 201);
    }

    public function updateIngredient(Request $request, Ingredient $ingredient): JsonResponse
    {
        $data = $request->validate([
            'name'          => 'sometimes|string|max:255',
            'unit'          => 'sometimes|string|max:20',
            'stock'         => 'sometimes|numeric|min:0',
            'reorder_level' => 'sometimes|numeric|min:0',
            'cost'          => 'sometimes|numeric|min:0',
            'supplier_id'   => 'nullable|integer|exists:suppliers,id',
        ]);

        $ingredient->update($data);

        return $this->success($ingredient->fresh(), 'Ingredient updated');
    }

    public function adjustStock(Request $request, Ingredient $ingredient): JsonResponse
    {
        $data = $request->validate([
            'delta'  => 'required|numeric',
            'reason' => 'required|in:purchase,waste,adjustment',
        ]);

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
        $items = Ingredient::with('supplier:id,name')
            ->whereRaw('stock <= reorder_level')
            ->orderBy('name')
            ->get();

        return $this->success($items);
    }

    // ── Suppliers ────────────────────────────────────────────────────────────

    public function suppliers(): JsonResponse
    {
        return $this->success(Supplier::orderBy('name')->get());
    }

    public function storeSupplier(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name'         => 'required|string|max:255',
            'contact_name' => 'nullable|string|max:255',
            'phone'        => 'nullable|string|max:20',
            'email'        => 'nullable|email',
            'address'      => 'nullable|string',
        ]);

        $supplier = Supplier::create($data);

        return $this->success($supplier, 'Supplier created', 201);
    }
}
