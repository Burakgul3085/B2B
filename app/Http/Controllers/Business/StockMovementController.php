<?php

namespace App\Http\Controllers\Business;

use App\Http\Controllers\Controller;
use App\Models\StockMovement;
use App\Models\Warehouse;
use App\Rules\NotBefore;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class StockMovementController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'warehouse' => ['nullable', 'integer'],
            'type' => ['nullable', Rule::in(array_keys(StockMovement::TYPES))],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', new NotBefore('from')],
            'product' => ['nullable', 'integer'],
        ]);

        $movements = StockMovement::query()
            ->with(['product:id,name,sku,unit_id', 'product.unit:id,name', 'warehouse:id,name'])
            ->when($filters['product'] ?? null, fn ($q, $id) => $q->where('product_id', $id))
            ->when($filters['q'] ?? null, function ($q, $term) {
                $like = addcslashes($term, '%_\\');
                $q->whereHas('product', fn ($p) => $p->where('name', 'like', "%{$like}%")->orWhere('sku', 'like', "{$like}%")->orWhere('barcode', $term));
            })
            ->when($filters['warehouse'] ?? null, fn ($q, $w) => $q->where('warehouse_id', $w))
            ->when($filters['type'] ?? null, fn ($q, $t) => $q->where('type', $t))
            ->when($filters['from'] ?? null, fn ($q, $d) => $q->where('created_at', '>=', $d.' 00:00:00'))
            ->when($filters['to'] ?? null, fn ($q, $d) => $q->where('created_at', '<=', $d.' 23:59:59'))
            ->latest('id')
            ->paginate(40)
            ->withQueryString();

        return view('business.inventory.movements', [
            'movements' => $movements,
            'filters' => $filters,
            'warehouses' => Warehouse::orderBy('name')->pluck('name', 'id'),
        ]);
    }
}
