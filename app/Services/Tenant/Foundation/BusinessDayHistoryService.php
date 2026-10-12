<?php

namespace App\Services\Tenant\Foundation;

use App\Models\Tenant\Branch;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

class BusinessDayHistoryService
{
    public function history(Branch $branch, array $range, int $perPage): array
    {
        $orders = DB::connection('tenant')->table('orders')
            ->where('orders.branch_id', $branch->id)
            ->whereNotIn('orders.status', ['draft', 'cancelled'])
            ->whereIn('orders.business_date_id', $this->days($branch, $range)->select('days.id'))
            ->select('orders.business_date_id')
            ->selectRaw('COUNT(*) AS orders_count')
            ->selectRaw("COALESCE(SUM(CASE WHEN orders.status = 'closed' THEN orders.subtotal_amount - orders.discount_amount ELSE 0 END), 0) AS sales_amount")
            ->groupBy('orders.business_date_id');

        // Refunds contain a gross amount; attribute its merchandise portion proportionally.
        $refunds = DB::connection('tenant')->table('refunds')
            ->join('payments', 'payments.id', '=', 'refunds.payment_id')
            ->join('orders', 'orders.id', '=', 'payments.order_id')
            ->where('refunds.branch_id', $branch->id)
            ->where('orders.branch_id', $branch->id)
            ->where('orders.status', 'closed')
            ->where('refunds.status', 'completed')
            ->whereIn('orders.business_date_id', $this->days($branch, $range)->select('days.id'))
            ->select('orders.business_date_id')
            ->selectRaw('ROUND(COALESCE(SUM(refunds.amount * (orders.subtotal_amount - orders.discount_amount) / NULLIF(orders.total_amount * 1.0, 0)), 0), 2) AS refund_amount')
            ->groupBy('orders.business_date_id');

        $query = $this->days($branch, $range)
            ->leftJoinSub($orders, 'order_totals', 'order_totals.business_date_id', '=', 'days.id')
            ->leftJoinSub($refunds, 'refund_totals', 'refund_totals.business_date_id', '=', 'days.id');

        $summary = (clone $query)
            ->selectRaw('COALESCE(SUM(COALESCE(order_totals.sales_amount, 0) - COALESCE(refund_totals.refund_amount, 0)), 0) AS net_sales_amount')
            ->selectRaw('COALESCE(SUM(order_totals.orders_count), 0) AS orders_count')
            ->selectRaw("COALESCE(SUM(CASE WHEN days.status IN ('open', 'closing') THEN 1 ELSE 0 END), 0) AS open_business_days_count")
            ->first();

        $page = $query->select(['days.uuid', 'days.business_date', 'days.business_day_number', 'days.status', 'days.opened_at', 'days.closed_at'])
            ->selectRaw('COALESCE(order_totals.orders_count, 0) AS orders_count')
            ->selectRaw('COALESCE(order_totals.sales_amount, 0) - COALESCE(refund_totals.refund_amount, 0) AS net_sales_amount')
            ->orderByDesc('days.business_date')->orderByDesc('days.id')
            ->paginate($perPage)->withQueryString();

        return ['summary' => [
            'net_sales_amount' => bcadd((string) $summary->net_sales_amount, '0', 2),
            'orders_count' => (int) $summary->orders_count,
            'open_business_days_count' => (int) $summary->open_business_days_count,
        ], 'page' => $page];
    }

    private function days(Branch $branch, array $range): Builder
    {
        return DB::connection('tenant')->table('business_dates as days')
            ->where('days.branch_id', $branch->id)
            ->whereBetween('days.business_date', [$range['from'], $range['to']]);
    }
}
