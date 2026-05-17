<?php

namespace App\Services;

use App\Http\Requests\ConfirmPaymentRequest;
use App\Http\Requests\StoreTransactionRequest;
use App\Models\Discount;
use App\Models\Outlet;
use App\Models\Product;
use App\Models\Shift;
use App\Models\Stock;
use App\Models\StockMutation;
use App\Models\TaxSetting;
use App\Models\Transaction;
use App\Models\TransactionDiscount;
use App\Models\TransactionItem;
use App\Models\TransactionTax;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class TransactionService
{
    // ─── Create

    public function create(StoreTransactionRequest $request, User $authUser): Transaction
    {
        $shift = Shift::findOrFail($request->shift_id);

        if ($shift->status !== 'open') {
            abort(422, 'Shift is closed, cannot make transactions');
        }

        $outlet = Outlet::findOrFail($shift->outlet_id);

        if ((int) $outlet->business_id !== (int) $authUser->business_id) {
            abort(403, 'Unauthorized access to this outlet');
        }
        $productIds = collect($request->items)->pluck('product_id')->unique();
        $products = Product::whereIn('id', $productIds)
            ->where('business_id', $authUser->business_id)
            ->where('is_active', true)
            ->get()
            ->keyBy('id');

        foreach ($request->items as $item) {
            if (!$products->has($item['product_id'])) {
                abort(422, "Produk ID {$item['product_id']} not found or inactive");
            }
        }

        $stockMap = $this->validateAndLoadStocks($request->items, $shift->outlet_id);
        $subtotal = $this->calculateSubtotal($request->items, $products);
        $discountResult = $this->validateAndCalculateDiscounts(
            $request->discount_codes ?? [],
            $subtotal,
            $authUser->business_id
        );

        $taxableAmount = $subtotal - $discountResult['total_discount'];
        $taxResult = $this->calculateTaxes($taxableAmount, $authUser->business_id);
        $total = $taxableAmount + $taxResult['total_tax'];
        $paymentStatus = $request->payment_method === 'qris' ? 'pending' : 'paid';

        return DB::transaction(function () use (
            $request, 
            $authUser, 
            $shift, 
            $outlet, 
            $products, 
            $stockMap, 
            $subtotal, 
            $discountResult, 
            $taxResult, 
            $total, 
            $paymentStatus
            ) {
            $transactionCode = $this->generateTransactionCode($outlet);

            $transaction = Transaction::create([
                'transaction_code' => $transactionCode,
                'user_id' => $authUser->id,
                'outlet_id' => $outlet->id,
                'shift_id' => $shift->id,
                'subtotal' => $subtotal,
                'discount_amount' => $discountResult['total_discount'],
                'tax_amount' => $taxResult['total_tax'],
                'total' => $total,
                'payment_method' => $request->payment_method,
                'payment_status' => $paymentStatus,
                'notes' => $request->notes,
            ]);

            foreach ($request->items as $item) {
                $product = $products[$item['product_id']];
                $variantId = $item['variant_id'] ?? null;
                $unitPrice = $product->price;
                $itemSubtotal = $unitPrice * $item['quantity'];

                TransactionItem::create([
                    'transaction_id' => $transaction->id,
                    'product_id' => $product->id,
                    'variant_id' => $variantId,
                    'product_name' => $product->name,
                    'unit_price' => $unitPrice,
                    'quantity' => $item['quantity'],
                    'subtotal' => $itemSubtotal,
                ]);

                $stockKey = $product->id . '-' . ($variantId ?? 0);
                $stock = $stockMap[$stockKey];

                if ($paymentStatus === 'paid') {
                    $quantityBefore = $stock->quantity;
                    $stock->decrement('quantity', $item['quantity']);

                    StockMutation::create([
                        'stock_id' => $stock->id,
                        'type' => 'sale',
                        'quantity_change' => -$item['quantity'],
                        'quantity_before' => $quantityBefore,
                        'quantity_after' => $quantityBefore - $item['quantity'],
                        'reference_id' => $transaction->id,
                        'reference_type' => 'transaction',
                        'user_id' => $authUser->id,
                        'notes' => "Penjualan #{$transactionCode}",
                    ]);
                } else if($paymentStatus === 'pending'){
                    $stock->increment('reserved_quantity', $item['quantity']);
                }
            }

            foreach ($discountResult['discounts'] as $discount) {
                TransactionDiscount::create([
                    'transaction_id' => $transaction->id,
                    'discount_id' => $discount['discount_id'],
                    'discount_code' => $discount['code'],
                    'discount_amount' => $discount['amount'],
                ]);

                if ($discount['discount_id']) {
                    Discount::where('id', $discount['discount_id'])->increment('used_count');
                }
            }

            foreach ($taxResult['taxes'] as $t) {
                TransactionTax::create([
                    'transaction_id' => $transaction->id,
                    'tax_settings_id' => $t['tax_settings_id'],
                    'tax_name' => $t['name'],
                    'tax_rate' => $t['rate'],
                    'tax_amount' => $t['amount'],
                ]);
            }

            return $transaction->load(['items', 'discounts', 'taxes', 'outlet:id,name,code']);
        });
    }

    // Confirm
    public function confirmPayment(ConfirmPaymentRequest $request, Transaction $transaction, User $authUser): Transaction
    {
        $this->authorizeTransaction($transaction, $authUser);

        if ($transaction->payment_status !== 'pending') {
            abort(422, 'This transaction is not in pending status');
        }

        return DB::transaction(function () use ($request, $transaction, $authUser) {
            $transaction->update([
                'payment_status' => 'paid',
                'payment_method' => $request->payment_method,
            ]);

            foreach ($transaction->items as $item) {
                $variantId = $item->variant_id;
                $stock = Stock::where('product_id', $item->product_id)
                    ->where('outlet_id', $transaction->outlet_id)
                    ->where('variant_id', $variantId ?? 0)
                    ->lockForUpdate()
                    ->first();

                if (!$stock || $stock->quantity < $item->quantity) {
                    abort(422, "Insufficient stock of the product: {$item->product_name}");
                }

                $quantityBefore = $stock->quantity;
                $stock->decrement('quantity', $item->quantity);
                $stock->decrement('reserved_quantity', $item->quantity);

                StockMutation::create([
                    'stock_id' => $stock->id,
                    'type' => 'sale',
                    'quantity_change' => -$item->quantity,
                    'quantity_before' => $quantityBefore,
                    'quantity_after' => $quantityBefore - $item->quantity,
                    'reference_id' => $transaction->id,
                    'reference_type' => 'transaction',
                    'user_id' => $authUser->id,
                    'notes' => "Penjualan #{$transaction->transaction_code}",
                ]);
            }

            return $transaction->fresh(['items', 'discounts', 'taxes', 'outlet:id,name,code']);
        });
    }

    public function cancel(Transaction $transaction, User $authUser){
        $this->authorizeTransaction($transaction, $authUser);

        if($transaction->payment_status !== 'pending'){
            abort(422, 'This transaction is not in pending status');
        }

        return DB::transaction(function () use($transaction){
            $transaction->update(['payment_status' => 'cancelled']);

            foreach ($transaction->items as $item) {
                $variantId = $item->variant_id;
                $stock = Stock::where('product_id', $item->product_id)
                    ->where('outlet_id', $transaction->outlet_id)
                    ->where('variant_id', $variantId ?? 0)
                    ->lockForUpdate()
                    ->first();

                $stock->decrement('reserved_quantity', $item->quantity);
            }

            foreach ($transaction->discounts as $td){
                if($td->discount_id){
                    Discount::where('id', $td->discount_id)->decrement('used_count');

                }
            }
            return $transaction->fresh(['items', 'discounts', 'outlet:id,name,code']);
        });
    }

    // Get All

    public function getAll(User $authUser): LengthAwarePaginator
    {
        $perPage = min((int) request('per_page', 15), 100);

        return Transaction::query()
            ->whereHas('outlet', fn($q) => $q->where('business_id', $authUser->business_id))
            ->with(['user:id,name', 'outlet:id,name', 'shift:id,opened_at'])
            ->when(request('outlet_id'), fn($q) => $q->where('outlet_id', request('outlet_id')))
            ->when(request('shift_id'), fn($q) => $q->where('shift_id', request('shift_id')))
            ->when(request('payment_method'), fn($q) => $q->where('payment_method', request('payment_method')))
            ->when(request('payment_status'), fn($q) => $q->where('payment_status', request('payment_status')))
            ->when(request('date_from'), fn($q) => $q->whereDate('created_at', '>=', request('date_from')))
            ->when(request('date_to'), fn($q) => $q->whereDate('created_at', '<=', request('date_to')))
            ->latest('created_at')
            ->paginate($perPage);
    }

    // Get Detail

    public function getDetail(Transaction $transaction, User $authUser): Transaction
    {
        $this->authorizeTransaction($transaction, $authUser);

        return $transaction->load([
            'user:id,name',
            'outlet:id,name,code',
            'shift:id,opened_at,closed_at',
            'items',
            'discounts',
            'taxes',
        ]);
    }

    // Helpers

    private function validateAndLoadStocks(array $items, int $outletId): array
    {
        $stockMap = [];

        foreach ($items as $item) {
            $variantId = $item['variant_id'] ?? 0;
            $stockKey = $item['product_id'] . '-' . $variantId;

            $stock = Stock::where('product_id', $item['product_id'])
                ->where('outlet_id', $outletId)
                ->where('variant_id', $variantId)
                ->lockForUpdate()
                ->first();

            if (!$stock) {
                abort(422, "Stock for product ID {$item['product_id']} was not found in this outlet");
            }

            $available_stock = $stock->available_quantity;

            if ($available_stock < $item['quantity']) {
                abort(422, "Insufficient stock for product ID {$item['product_id']}. Available: {$available_stock}");
            }

            $stockMap[$stockKey] = $stock;
        }

        return $stockMap;
    }

    private function calculateSubtotal(array $items, $products): int
    {
        $subtotal = 0;
        foreach ($items as $item) {
            $subtotal += $products[$item['product_id']]->price * $item['quantity'];
        }
        return $subtotal;
    }

    private function validateAndCalculateDiscounts(array $codes, int $subtotal, int $businessId): array
    {
        $discounts = [];
        $totalDiscount = 0;
        $now = now();

        foreach ($codes as $code) {
            $discount = Discount::where('code', $code)
                ->where('business_id', $businessId)
                ->where('is_active', true)
                ->lockForUpdate()
                ->first();

            if (!$discount) {
                abort(422, "Discount code '{$code}' is invalid");
            }

            if ($discount->valid_from && $now->lt($discount->valid_from)) {
                abort(422, "Discount code '{$code}' is not valid yet");
            }

            if ($discount->valid_until && $now->gt($discount->valid_until)) {
                abort(422, "Discount code '{$code}' has expired");
            }

            if ($discount->max_uses !== null && $discount->used_count >= $discount->max_uses) {
                abort(422, "Discount code '{$code}' has reached its usage limit");
            }

            if ($subtotal < $discount->min_purchase) {
                abort(422, "The minimum purchase for the '{$code}' discount is Rp " . number_format($discount->min_purchase, 0, ',', '.'));
            }

            $amount = $discount->type === 'percentage'
                ? (int) round($subtotal * $discount->value / 100)
                : $discount->value;

            $discounts[] = [
                'discount_id' => $discount->id,
                'code' => $code,
                'amount' => $amount,
            ];
            $totalDiscount += $amount;
        }

        $totalDiscount = min($totalDiscount, $subtotal);

        return [
            'discounts' => $discounts,
            'total_discount' => $totalDiscount,
        ];
    }

    private function calculateTaxes(int $taxableAmount, int $businessId): array
    {
        $taxes = [];
        $totalTax = 0;

        $activeTaxes = TaxSetting::where('business_id', $businessId)
            ->where('is_active', true)
            ->get();

        foreach ($activeTaxes as $tax) {
            $amount = (int) round($taxableAmount * $tax->rate / 100);
            $taxes[] = [
                'tax_settings_id' => $tax->id,
                'name' => $tax->name,
                'rate' => $tax->rate,
                'amount' => $amount,
            ];
            $totalTax += $amount;
        }

        return [
            'taxes' => $taxes,
            'total_tax' => $totalTax,
        ];
    }

    private function generateTransactionCode(Outlet $outlet): string
    {
        $date = now()->format('Ymd');
        $prefix = $outlet->code . '-' . $date . '-';

        $last = Transaction::where('transaction_code', 'like', $prefix . '%')
            ->lockForUpdate()
            ->orderByDesc('transaction_code')
            ->value('transaction_code');

        $sequence = $last ? (int) substr($last, -4) + 1 : 1;

        return $prefix . str_pad($sequence, 4, '0', STR_PAD_LEFT);
    }

    private function authorizeTransaction(Transaction $transaction, User $authUser): void
    {
        $outlet = Outlet::findOrFail($transaction->outlet_id);

        if ((int) $outlet->business_id !== (int) $authUser->business_id) {
            abort(403, 'Unauthorized access to this transaction');
        }
    }
}
