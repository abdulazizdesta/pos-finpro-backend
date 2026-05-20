<?php

namespace App\Services;

use App\Exports\ArrayExport;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

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
        COUNT(*) as total_transactions,
        COALESCE(SUM(total), 0) as total_revenue,
        COALESCE(AVG(total), 0) as average_per_transaction
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

    public function exportSales(User $authUser)
    {
        $rows = $this->baseQuery($authUser->business_id)
            ->with(['user:id,name', 'outlet:id,name', 'shift:id'])
            ->select('transaction_code', 'created_at', 'payment_method', 'subtotal', 'total', 'user_id', 'outlet_id')
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(fn($t) => [
                'Tanggal' => $t->created_at->format('Y-m-d H:i'),
                'Kode Transaksi' => $t->transaction_code,
                'Kasir' => $t->user?->name ?? '-',
                'Outlet' => $t->outlet?->name ?? '-',
                'Metode Pembayaran' => $t->payment_method,
                'Subtotal' => (float) $t->subtotal,
                'Total' => (float) $t->total,
            ]);

        $data = [];
        $data[] = ['LAPORAN TRANSAKSI'];
        $data[] = ['Periode', request('date_from', '-') . ' s/d ' . request('date_to', '-')];
        $data[] = [];
        $data[] = array_keys($rows->first() ?? []); 

        foreach ($rows as $row) {
            $data[] = array_values($row);
        }

        return Excel::download(
            new ArrayExport($data),
            'transaksi-' . now()->format('Y-m-d') . '.xlsx'
        );
    }
}
