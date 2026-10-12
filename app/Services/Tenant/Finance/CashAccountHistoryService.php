<?php

namespace App\Services\Tenant\Finance;

use App\Models\Tenant\Branch;
use App\Models\Tenant\BusinessDayCashReconciliation;
use App\Models\Tenant\CashAccount;
use App\Models\Tenant\CashMovement;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class CashAccountHistoryService
{
    public function history(Branch $branch, CashAccount $account, array $range, int $perPage): array
    {
        $query = BusinessDayCashReconciliation::query()
            ->join('business_dates as days', 'days.id', '=', 'business_day_cash_reconciliations.business_date_id')
            ->where('business_day_cash_reconciliations.branch_id', $branch->id)
            ->where('days.branch_id', $branch->id)
            ->where('business_day_cash_reconciliations.cash_account_id', $account->id)
            ->whereBetween('days.business_date', [$range['from'], $range['to']]);

        $summary = (clone $query)->where('business_day_cash_reconciliations.status', 'closed')
            ->selectRaw('COALESCE(SUM(business_day_cash_reconciliations.expected_cash_amount), 0) AS expected_cash_amount')
            ->selectRaw('COALESCE(SUM(business_day_cash_reconciliations.counted_cash_amount), 0) AS counted_cash_amount')
            ->selectRaw('COALESCE(SUM(business_day_cash_reconciliations.variance_amount), 0) AS variance_amount')
            ->first();

        $page = $query->select(['business_day_cash_reconciliations.*', 'days.business_date'])
            ->orderByDesc('days.business_date')->orderByDesc('business_day_cash_reconciliations.id')
            ->paginate($perPage)->withQueryString();

        return ['summary' => [
            'expected_cash_amount' => bcadd((string) $summary->expected_cash_amount, '0', 2),
            'counted_cash_amount' => bcadd((string) $summary->counted_cash_amount, '0', 2),
            'variance_amount' => bcadd((string) $summary->variance_amount, '0', 2),
        ], 'page' => $page];
    }

    public function movements(Branch $branch, BusinessDayCashReconciliation $reconciliation, int $perPage): LengthAwarePaginator
    {
        return CashMovement::query()
            ->where('branch_id', $branch->id)
            ->where('cash_account_id', $reconciliation->cash_account_id)
            ->where('business_date_id', $reconciliation->business_date_id)
            ->orderByDesc('moved_at')->orderByDesc('id')
            ->paginate($perPage)->withQueryString();
    }
}
