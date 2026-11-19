<?php



namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateOrderStatusRequest;
use App\Http\Resources\OrderResource;
use App\Models\Order;
use App\Services\OrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OrderController extends Controller
{
    public function __construct(private readonly OrderService $orders)
    {
    }

    /** GET /api/admin/orders?status=pending|delivered|cancelled&search=&from=&to=&per_page= */
    public function index(Request $request)
    {
        $request->validate([
            'status' => ['nullable', 'in:pending,delivered,cancelled,all,Pending,Delivered,Cancelled,All'],
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $query = Order::query()->with('items')->latest('id');

        $status = strtolower((string) $request->query('status', ''));
        if ($status !== '' && $status !== 'all') {
            $query->where('status', $status);
        }

        if (($search = trim((string) $request->query('search', ''))) !== '') {
            $like = '%'.addcslashes($search, '%_\\').'%';
            $query->where(function ($q) use ($search, $like) {
                $q->where('customer_name', 'like', $like)->orWhere('customer_email', 'like', $like);
                if (ctype_digit(ltrim($search, '#'))) {
                    $q->orWhere('order_number', (int) ltrim($search, '#'));
                }
            });
        }

        if ($request->filled('from')) {
            $query->whereDate('created_at', '>=', $request->query('from'));
        }
        if ($request->filled('to')) {
            $query->whereDate('created_at', '<=', $request->query('to'));
        }

        return OrderResource::collection($query->paginate((int) $request->query('per_page', 20))->withQueryString());
    }

    /** GET /api/admin/orders/{orderNumber} */
    public function show(Order $order): JsonResponse
    {
        return response()->json(['data' => (new OrderResource($order->load('items')))->resolve()]);
    }

    /** PATCH /api/admin/orders/{orderNumber}/status   body: status = Pending | Delivered | Cancelled */
    public function updateStatus(UpdateOrderStatusRequest $request, Order $order): JsonResponse
    {
        $order = $this->orders->changeStatus($order, $request->validated('status'));

        return response()->json([
            'message' => 'Order status updated.',
            'data' => (new OrderResource($order))->resolve(),
        ]);
    }
}
