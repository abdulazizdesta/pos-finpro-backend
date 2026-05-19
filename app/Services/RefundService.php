<?php

namespace App\Services;

use App\Http\Requests\StoreRefundRequest;
use App\Models\Outlet;
use App\Models\Refund;
use App\Models\RefundItem;
use App\Models\Stock;
use App\Models\StockMutation;
use App\Models\Transaction;
use App\Models\TransactionItem;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class RefundService
{
    public function __construct()
    {

    }

    public function create(StoreRefundRequest $request, Transaction $transaction, User $authUser): Refund
    {
        $this->authorizeTransaction($transaction, $authUser);

        $transaction->refresh();
        
        if ($transaction->payment_status !== 'paid') {
            abort(422, 'transaction has not been paid');
        }

        if ($transaction->created_at->diffInHours(now()) > 24) {
            abort(422, 'the refund deadline has been reached');
        }

        $transactionItems = [];
        $transactionTotal = $transaction->total;
        $transactionSubTotal = $transaction->subtotal;

        foreach ($request->items as $item) {
            $transactionItem = TransactionItem::where('id', $item['transaction_item_id'])
                ->where('transaction_id', $transaction->id)
                ->first();

            if (!$transactionItem) {
                abort(422, 'Item does not belong to this transaction');
            }

            if ($item['quantity'] > $transactionItem->refundable_quantity) {
                abort(422, "Refund quantity exceeds refundable quantity for item {$transactionItem->product_name}. Refundable: {$transactionItem->refundable_quantity}");
            }

            $itemRatio = $transactionItem->unit_price * $item['quantity'] / $transactionSubTotal;
            $itemRefundAmount = round($transactionTotal * $itemRatio);

            $transactionItems[$item['transaction_item_id']] = [
                'model' => $transactionItem,
                'quantity' => $item['quantity'],
                'amount' => $itemRefundAmount,
            ];

        }

        $totalRefundAmount = array_sum(array_column($transactionItems, 'amount'));

        return DB::transaction(
            function () use ($authUser, $totalRefundAmount, $request, $transaction, $transactionItems, ) {
                $transaction->update([
                    'payment_status' => 'refunded',
                ]);
                $refund = Refund::create([
                    'amount' => $totalRefundAmount,
                    'reason' => $request->reason,
                    'processed_by' => $authUser->id,
                    'transaction_id' => $transaction->id,
                ]);

                foreach ($transactionItems as $transactionItemId => $t) {

                    RefundItem::create([
                        'refund_id' => $refund->id,
                        'transaction_item_id' => $transactionItemId,
                        'quantity' => $t['quantity'],
                        'amount' => $t['amount']
                    ]);

                    $t['model']->increment('refunded_quantity', $t['quantity']);

                    $stock = Stock::where('product_id', $t['model']->product_id)
                        ->where('outlet_id', $transaction->outlet_id)
                        ->where('variant_id', $t['model']->variant_id ?? 0)
                        ->lockForUpdate()
                        ->first();

                    $quantityBefore = $stock->quantity;
                    $stock->increment('quantity', $t['quantity']);

                    StockMutation::create([
                        'stock_id' => $stock->id,
                        'type' => 'refund',
                        'quantity_change' => +$t['quantity'],
                        'quantity_before' => $quantityBefore,
                        'quantity_after' => $quantityBefore + $t['quantity'],
                        'reference_id' => $transaction->id,
                        'reference_type' => 'refund',
                        'user_id' => $authUser->id,
                        'notes' => "Refund #{$transaction->transaction_code}",
                    ]);
                }

                return $refund->load([
                    'items',
                    'items.transactionItem',
                    'processedBy:id,name',
                    'transaction:id,transaction_code,total',
                ]);

            }

        );

    }

    private function authorizeTransaction(Transaction $transaction, User $authUser): void
    {
        $outlet = Outlet::findOrFail($transaction->outlet_id);

        if ((int) $outlet->business_id !== (int) $authUser->business_id) {
            abort(403, 'Unauthorized access to this transaction');
        }
    }
}
