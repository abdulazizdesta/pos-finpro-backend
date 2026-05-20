<?php

namespace App\Services;

use App\Models\Transaction;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class ReportService
{
    /**
     * Create a new class instance.
     */
    public function __construct()
    {
        //
    }

    private function baseQuery(string $businessId)
    {
        return Transaction::query()
            ->whereHas('outlet', fn($q) => $q->where('business_id', $businessId))
            ->where('payment_status', 'paid')
            ->when(request('outlet_id'), fn($q) => $q->where('outlet_id', request('outlet_id')))
            ->when(request('date_from'), fn($q) => $q->whereDate('created_at', '>=', request('date_from')))
            ->when(request('date_to'), fn($q) => $q->whereDate('created_at', '<=', request('date_to')));
    }

    public function sales(User $authUser): array
    {
        $baseQuery = $this->baseQuery($authUser->business_id);

        $summary = (clone $baseQuery)->selectRaw('
        COUNT * as total_transactions,
        COALESCE(SUM(total), 0) as total_revenue,
        COALESCE(AVG(total), 0) as average_per_transactions
        ')->first();

        $summaryByDate = (clone $baseQuery)
            ->selectRaw('
            DATE(created_at) as date, COUNT(*) as transactions, SUM(total) as revenue
        ')->groupBy(DB::raw('DATE(created_at)'))->orderBy('date')->get();

        return [
            'summary' => [
                'total_transactions' => (int) $summary->total_transactions,
                'total_revenue' => (int) $summary->total_revenue,
                'average_per_transaction' => (float) $summary->average_per_transaction,
            ],
            'summary_by_date' => $summaryByDate,
        ];
    }
}
