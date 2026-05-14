<?php

namespace App\Services;

use App\Http\Requests\AdjustStockRequest;
use App\Http\Requests\RestockRequest;
use App\Http\Requests\StoreStockRequest;
use App\Models\Outlet;
use App\Models\Stock;
use App\Models\StockMutation;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class StockService
{
    public function create(StoreStockRequest $request, User $authUser): Stock
    {
        $exists = Stock::where('product_id', $request->product_id)
            ->where('outlet_id', $request->outlet_id)
            ->where('variant_id', 0)
            ->exists();

        if ($exists) {
            abort(422, 'Stock for this product and outlet already exists');
        }

        $this->authorizeOutlet($authUser, $request->outlet_id);

        return DB::transaction(
            function () use ($request, $authUser) {

                $stock = Stock::create([
                    'product_id' => $request->product_id,
                    'variant_id' => 0,
                    'outlet_id' => $request->outlet_id,
                    'quantity' => $request->quantity,
                    'min_threshold' => $request->min_threshold ?? 5,
                ]);

                $this->recordMutation(
                    $stock,
                    'restock',
                    $request->quantity,
                    0,
                    $authUser->id,
                    'initial_stock'
                );
                return $stock;
            }
        );
    }

    public function restock(RestockRequest $request, Stock $stock, User $authUser): Stock
    {
        $this->authorizeOutlet($authUser, $stock->outlet_id);

        return DB::transaction(function () use ($request, $stock, $authUser) {
            $quantityBefore = $stock->quantity;

            $stock->update([
                'quantity' => $quantityBefore + $request->quantity,
            ]);

            $this->recordMutation(
                $stock,
                'restock',
                $request->quantity,
                $quantityBefore,
                $authUser->id,
                $request->notes ?? 'Manual restock',
            );

            return $stock->fresh();
        });
    }

    public function adjust(AdjustStockRequest $request, Stock $stock, User $authUser): Stock
    {
        $this->authorizeOutlet($authUser, $stock->outlet_id);

        $quantityBefore = $stock->quantity;
        $quantityAfter = $quantityBefore + $request->quantity_change;

        if ($quantityAfter < 0) {
            abort(422, "Cannot reduce stock below zero. Current stock: {$quantityBefore}");
        }

        return DB::transaction(function () use ($request, $stock, $authUser, $quantityBefore) {
            $stock->update([
                'quantity' => $quantityBefore + $request->quantity_change,
            ]);

            $this->recordMutation(
                $stock,
                'adjustment',
                $request->quantity_change,
                $quantityBefore,
                $authUser->id,
                $request->notes,
            );

            return $stock->fresh();
        });
    }

    public function getAll(User $authUser): LengthAwarePaginator
    {
        $perPage = min((int) request('per_page', 15), 100);

        $query = Stock::query()
            ->with(['outlet:id,name', 'product:id,sku,name'])
            ->whereHas('outlet', fn($q) => $q->where('business_id', $authUser->business_id))
            ->when(request('outlet_id'), fn($q) => $q->where('outlet_id', request('outlet_id')))
            ->when(request('product_id'), fn($q) => $q->where('product_id', request('product_id')));

        return $query->paginate($perPage);
    }

    public function getDetail(Stock $stock, User $authUser): Stock
    {
        $this->authorizeOutlet($authUser, $stock->outlet_id);

        $stock->load([
            'outlet:id,name',
            'product:id,sku,name',
            'mutations' => fn($q) => $q->with('user:id,name')
                ->latest()
                ->take(10),
        ]);

        $stock->low_stock = $stock->quantity <= $stock->min_threshold;

        return $stock;
    }

    public function getMutations(User $authUser): LengthAwarePaginator
    {
        $perPage = min((int) request('per_page', 15), 100);

        $query = StockMutation::query()
            ->whereHas('stock.outlet', fn($q) => $q->where('business_id', $authUser->business_id))
            ->with([
                'stock:id,product_id,outlet_id,quantity',
                'stock.product:id,name,sku',
                'stock.outlet:id,name',
                'user:id,name',
            ])
            ->when(request('outlet_id'), fn($q) => $q->whereHas('stock', fn($q) => $q->where('outlet_id', request('outlet_id'))))
            ->when(request('product_id'), fn($q) => $q->whereHas('stock', fn($q) => $q->where('product_id', request('product_id'))))
            ->when(request('type'), fn($q) => $q->where('type', request('type')))
            ->when(request('date_from'), fn($q) => $q->whereDate('created_at', '>=', request('date_from')))
            ->when(request('date_to'), fn($q) => $q->whereDate('created_at', '<=', request('date_to')));

        return $query->latest('created_at')->paginate($perPage);
    }

    private function authorizeOutlet(User $authUser, int $outletId): void
    {

        $outlet = Outlet::findOrFail($outletId);

        if ((int) $authUser->business_id !== (int) $outlet->business_id) {
            abort(403, 'Unauthorized access to this outlet');
        }
    }

    private function recordMutation(
        Stock $stock,
        string $type,
        int $quantityChange,
        int $quantityBefore,
        int $userId,
        string $notes = '',
        ?int $referenceId = null,
        ?string $referenceType = null,
    ): StockMutation {
        return StockMutation::create([
            'stock_id' => $stock->id,
            'type' => $type,
            'quantity_change' => $quantityChange,
            'quantity_before' => $quantityBefore,
            'quantity_after' => $quantityBefore + $quantityChange,
            'reference_id' => $referenceId,
            'reference_type' => $referenceType,
            'user_id' => $userId,
            'notes' => $notes,
        ]);
    }
}
