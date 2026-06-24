<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class ReportController extends Controller
{
    /**
     * GET /api/mobile/reports/sales-summary?period=month&location_id=
     *
     * Returns aggregated sales KPIs for the requested period.
     * period: today | week | month | year
     */
    public function salesSummary(Request $request): JsonResponse
    {
        $request->validate([
            'period'      => ['nullable', 'in:today,week,month,year'],
            'location_id' => ['nullable', 'integer'],
        ]);

        $period     = $request->query('period', 'month');
        $locationId = (int) $request->query('location_id', $request->user()->location_id ?? 1);
        $businessId = $request->user()->business_id;

        [$from, $to] = $this->getPeriodRange($period);

        $summary = DB::table('transactions')
            ->where('business_id', $businessId)
            ->where('type', 'sell')
            ->where('status', 'final')
            ->where('location_id', $locationId)
            ->whereBetween('transaction_date', [$from, $to])
            ->selectRaw('
                COUNT(*) as count,
                COALESCE(SUM(total_before_tax), 0)  as gross,
                COALESCE(SUM(discount_amount), 0)   as discount,
                COALESCE(SUM(tax_amount), 0)         as tax,
                COALESCE(SUM(final_total), 0)        as net
            ')
            ->first();

        return response()->json(['summary' => $summary, 'period' => $period]);
    }

    /**
     * GET /api/mobile/reports/top-products?period=month&location_id=&limit=10
     */
    public function topProducts(Request $request): JsonResponse
    {
        $period     = $request->query('period', 'month');
        $locationId = (int) $request->query('location_id', $request->user()->location_id ?? 1);
        $limit      = min(50, (int) $request->query('limit', 10));
        $businessId = $request->user()->business_id;

        [$from, $to] = $this->getPeriodRange($period);

        $products = DB::table('transaction_sell_lines as tsl')
            ->join('transactions as t', 't.id', '=', 'tsl.transaction_id')
            ->join('products as p', 'p.id', '=', 'tsl.product_id')
            ->join('variations as v', 'v.id', '=', 'tsl.variation_id')
            ->where('t.business_id', $businessId)
            ->where('t.location_id', $locationId)
            ->where('t.type', 'sell')
            ->where('t.status', 'final')
            ->whereBetween('t.transaction_date', [$from, $to])
            ->selectRaw('
                p.name as product_name,
                v.name as variation_name,
                SUM(tsl.quantity) as qty_sold,
                SUM(tsl.line_total) as total_revenue
            ')
            ->groupBy('tsl.variation_id', 'p.name', 'v.name')
            ->orderByDesc('total_revenue')
            ->limit($limit)
            ->get();

        return response()->json(['products' => $products]);
    }

    /**
     * GET /api/mobile/reports/payment-methods?period=month&location_id=
     */
    public function paymentMethods(Request $request): JsonResponse
    {
        $period     = $request->query('period', 'month');
        $locationId = (int) $request->query('location_id', $request->user()->location_id ?? 1);
        $businessId = $request->user()->business_id;

        [$from, $to] = $this->getPeriodRange($period);

        $methods = DB::table('transaction_payments as tp')
            ->join('transactions as t', 't.id', '=', 'tp.transaction_id')
            ->where('t.business_id', $businessId)
            ->where('t.location_id', $locationId)
            ->where('t.type', 'sell')
            ->where('t.status', 'final')
            ->whereBetween('t.transaction_date', [$from, $to])
            ->selectRaw('tp.method, SUM(tp.amount) as total_amount, COUNT(DISTINCT t.id) as transaction_count')
            ->groupBy('tp.method')
            ->orderByDesc('total_amount')
            ->get();

        return response()->json(['methods' => $methods]);
    }

    /**
     * GET /api/mobile/reports/daily-sales?location_id=
     *
     * Returns daily sales for the current month (for bar chart).
     */
    public function dailySales(Request $request): JsonResponse
    {
        $locationId = (int) $request->query('location_id', $request->user()->location_id ?? 1);
        $businessId = $request->user()->business_id;
        $from = Carbon::now()->startOfMonth();
        $to   = Carbon::now()->endOfMonth();

        $rows = DB::table('transactions')
            ->where('business_id', $businessId)
            ->where('location_id', $locationId)
            ->where('type', 'sell')
            ->where('status', 'final')
            ->whereBetween('transaction_date', [$from, $to])
            ->selectRaw("
                DATE(transaction_date) as date,
                COUNT(*) as transaction_count,
                COALESCE(SUM(total_before_tax), 0) as gross_total,
                COALESCE(SUM(discount_amount), 0)  as discount_amount,
                COALESCE(SUM(tax_amount), 0)       as tax_amount,
                COALESCE(SUM(final_total), 0)      as net_total
            ")
            ->groupByRaw('DATE(transaction_date)')
            ->orderBy('date')
            ->get();

        return response()->json(['rows' => $rows]);
    }

    /**
     * GET /api/mobile/reports/stock-value?location_id=
     */
    public function stockValue(Request $request): JsonResponse
    {
        $locationId = (int) $request->query('location_id', $request->user()->location_id ?? 1);
        $businessId = $request->user()->business_id;

        $rows = DB::table('products as p')
            ->join('variations as v', 'v.product_id', '=', 'p.id')
            ->leftJoin('variation_location_details as vld', function ($join) use ($locationId) {
                $join->on('vld.variation_id', '=', 'v.id')
                     ->where('vld.location_id', $locationId);
            })
            ->where('p.business_id', $businessId)
            ->where('p.is_inactive', 0)
            ->whereRaw('COALESCE(vld.qty_available, 0) > 0')
            ->selectRaw('
                p.name as product_name,
                v.name as variation_name,
                COALESCE(vld.qty_available, 0) as qty,
                v.dpp_inc_tax as unit_cost,
                v.default_sell_price as sell_price,
                COALESCE(vld.qty_available, 0) * COALESCE(v.dpp_inc_tax, 0) as stock_value,
                COALESCE(vld.qty_available, 0) * COALESCE(v.default_sell_price, 0) as potential_revenue
            ')
            ->orderByDesc('stock_value')
            ->get();

        $totalValue     = $rows->sum('stock_value');
        $totalPotential = $rows->sum('potential_revenue');

        return response()->json([
            'rows'              => $rows,
            'total_value'       => $totalValue,
            'total_potential'   => $totalPotential,
        ]);
    }

    // ─── Helpers ──────────────────────────────────────────────────────────────

    private function getPeriodRange(string $period): array
    {
        return match ($period) {
            'today' => [Carbon::today(), Carbon::today()->endOfDay()],
            'week'  => [Carbon::now()->startOfWeek(), Carbon::now()->endOfWeek()],
            'year'  => [Carbon::now()->startOfYear(), Carbon::now()->endOfYear()],
            default => [Carbon::now()->startOfMonth(), Carbon::now()->endOfMonth()], // month
        };
    }
}
