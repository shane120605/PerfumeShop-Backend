<?php


namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class SalesReportController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $request->validate([
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d'],
        ]);

        $from = Carbon::parse($request->query('from') ?: now()->startOfMonth()->toDateString())->startOfDay();
        $to = Carbon::parse($request->query('to') ?: now()->toDateString())->endOfDay();

        if ($to->lt($from)) {
            throw ValidationException::withMessages(['to' => ['The end date must be on or after the start date.']]);
        }

        $days = intdiv($to->copy()->startOfDay()->getTimestamp() - $from->getTimestamp(), 86400);
        if ($days > 1100) {
            throw ValidationException::withMessages(['to' => ['Please choose a range of 3 years or less.']]);
        }

        $orders = Order::where('status', '!=', Order::STATUS_CANCELLED)
            ->whereBetween('created_at', [$from, $to])
            ->get(['created_at', 'total']);

        $total = round((float) $orders->sum('total'), 2);
        $count = $orders->count();

        if ($days <= 93) {
            $unit = 'week';
            $buckets = [];
            for ($i = 0; $i <= intdiv($days, 7); $i++) {
                $start = $from->copy()->addDays($i * 7);
                $buckets[] = ['label' => $start->format('M j'), 'start' => $start->toDateString(), 'value' => 0.0];
            }
            foreach ($orders as $order) {
                $secs = $order->created_at->copy()->startOfDay()->getTimestamp() - $from->getTimestamp();
                $index = max(0, min(count($buckets) - 1, intdiv($secs, 604800)));
                $buckets[$index]['value'] += (float) $order->total;
            }
        } else {
            $unit = 'month';
            $map = [];
            $cursor = $from->copy()->startOfMonth();
            while ($cursor->lte($to)) {
                $map[$cursor->format('Y-m')] = ['label' => $cursor->format('M y'), 'start' => $cursor->toDateString(), 'value' => 0.0];
                $cursor->addMonthNoOverflow();
            }
            foreach ($orders as $order) {
                $key = $order->created_at->format('Y-m');
                if (isset($map[$key])) {
                    $map[$key]['value'] += (float) $order->total;
                }
            }
            $buckets = array_values($map);
        }

        foreach ($buckets as &$bucket) {
            $bucket['value'] = round($bucket['value'], 2);
        }
        unset($bucket);

        return response()->json(['data' => [
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
            'unit' => $unit,
            'total_sales' => $total,
            'total_orders' => $count,
            'average_order' => $count ? round($total / $count, 2) : 0.0,
            'buckets' => $buckets,
        ]]);
    }
}
