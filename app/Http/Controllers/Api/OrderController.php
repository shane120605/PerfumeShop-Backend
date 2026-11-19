<?php


namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\PlaceOrderRequest;
use App\Http\Resources\OrderResource;
use App\Models\Order;
use App\Services\OrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class OrderController extends Controller
{
    public function __construct(private readonly OrderService $orders)
    {
    }

    /** GET /api/orders  -> my order history (newest first) */
    public function index(Request $request)
    {
        $perPage = max(1, min(100, (int) $request->query('per_page', 15)));

        return OrderResource::collection(
            $request->user()->orders()->with('items')->latest('id')->paginate($perPage)
        );
    }

    /** GET /api/orders/{orderNumber} */
    public function show(Request $request, int $orderNumber): JsonResponse
    {
        $order = $this->ownOrder($request, $orderNumber);

        return response()->json(['data' => (new OrderResource($order))->resolve()]);
    }

    /**
     * POST /api/orders   (checkout)
     * Body: full_name, address, city, phone, payment_method, items[] (optional: [{product_id, quantity}])
     * If "items" is missing, the server-side cart is used.
     * Prices and totals are NEVER read from the request.
     */
    public function store(PlaceOrderRequest $request): JsonResponse
    {
        $user = $request->user();

        $lines = []; // [productId => quantity]
        if ($request->has('items')) {
            foreach ($request->validated('items') as $item) {
                $id = (int) $item['product_id'];
                $lines[$id] = ($lines[$id] ?? 0) + (int) $item['quantity'];
            }
        } else {
            $lines = $user->cartItems()->pluck('quantity', 'product_id')->map(fn ($q) => (int) $q)->all();
        }

        if (! $lines) {
            throw ValidationException::withMessages(['items' => ['Your cart is empty.']]);
        }

        $order = $this->orders->place(
            $user,
            $request->safe()->only(['full_name', 'address', 'city', 'phone']),
            $request->validated('payment_method'),
            $lines
        );

        return response()->json([
            'message' => 'Order placed successfully.',
            'data' => (new OrderResource($order))->resolve(),
        ], 201);
    }

    /** POST /api/orders/{orderNumber}/cancel  -> only while the order is still Pending; stock is returned */
    public function cancel(Request $request, int $orderNumber): JsonResponse
    {
        $order = $this->ownOrder($request, $orderNumber);
        $order = $this->orders->changeStatus($order, Order::STATUS_CANCELLED);

        return response()->json([
            'message' => 'Order cancelled.',
            'data' => (new OrderResource($order))->resolve(),
        ]);
    }

    private function ownOrder(Request $request, int $orderNumber): Order
    {
        return $request->user()->orders()->with('items')->where('order_number', $orderNumber)->firstOrFail();
    }
}
