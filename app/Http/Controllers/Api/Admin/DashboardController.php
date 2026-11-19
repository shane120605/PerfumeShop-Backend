<?php


namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\OrderResource;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Http\JsonResponse;

class DashboardController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $sales = Order::where('status', '!=', Order::STATUS_CANCELLED)->sum('total');

        return response()->json(['data' => [
            'total_products' => Product::count(),
            'total_orders' => Order::count(),
            'total_users' => User::count(),
            'total_customers' => User::where('role', User::ROLE_CUSTOMER)->count(),
            'total_sales' => round((float) $sales, 2), // cancelled orders are not counted
            'pending_orders' => Order::where('status', Order::STATUS_PENDING)->count(),
            'low_stock_products' => Product::where('stock', '<=', (int) config('shop.low_stock_threshold'))->count(),
            'recent_orders' => OrderResource::collection(
                Order::with('items')->latest('id')->limit(5)->get()
            )->resolve(),
        ]]);
    }
}
