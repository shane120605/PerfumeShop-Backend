<?php


namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\CartItemRequest;
use App\Http\Resources\ProductResource;
use App\Models\CartItem;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class CartController extends Controller
{
    /** GET /api/cart */
    public function index(Request $request): JsonResponse
    {
        return $this->cartResponse($request);
    }

    /** POST /api/cart/items   body: product_id, quantity  (adds to the existing quantity) */
    public function store(CartItemRequest $request): JsonResponse
    {
        $product = Product::findOrFail($request->validated('product_id'));
        $existing = CartItem::where('user_id', $request->user()->id)->where('product_id', $product->id)->value('quantity') ?? 0;

        $this->saveQuantity($request, $product, $existing + (int) $request->validated('quantity'));

        return $this->cartResponse($request, 'Item added to cart.', 201);
    }

    /** PUT /api/cart/items/{product}   body: quantity  (sets the quantity) */
    public function update(CartItemRequest $request, Product $product): JsonResponse
    {
        $this->saveQuantity($request, $product, (int) $request->validated('quantity'));

        return $this->cartResponse($request, 'Cart updated.');
    }

    /** DELETE /api/cart/items/{product} */
    public function destroy(Request $request, Product $product): JsonResponse
    {
        CartItem::where('user_id', $request->user()->id)->where('product_id', $product->id)->delete();

        return $this->cartResponse($request, 'Item removed from cart.');
    }

    /** DELETE /api/cart */
    public function clear(Request $request): JsonResponse
    {
        CartItem::where('user_id', $request->user()->id)->delete();

        return $this->cartResponse($request, 'Cart cleared.');
    }

    private function saveQuantity(Request $request, Product $product, int $quantity): void
    {
        if ($quantity > $product->stock) {
            throw ValidationException::withMessages([
                'quantity' => [$product->stock > 0
                    ? "Only {$product->stock} of {$product->name} left in stock."
                    : "{$product->name} is out of stock."],
            ]);
        }

        CartItem::updateOrCreate(
            ['user_id' => $request->user()->id, 'product_id' => $product->id],
            ['quantity' => $quantity]
        );
    }

    private function cartResponse(Request $request, ?string $message = null, int $status = 200): JsonResponse
    {
        $items = CartItem::where('user_id', $request->user()->id)
            ->with('product.category')
            ->orderBy('id')
            ->get()
            ->filter(fn (CartItem $i) => $i->product !== null);

        $lines = $items->map(fn (CartItem $i) => [
            'productId' => $i->product_id,
            'quantity' => $i->quantity,
            'lineTotal' => round((float) $i->product->price * $i->quantity, 2),
            'product' => (new ProductResource($i->product))->resolve(),
        ])->values();

        return response()->json(array_filter([
            'message' => $message,
            'data' => [
                'items' => $lines,
                'count' => (int) $items->sum('quantity'),
                'subtotal' => round((float) $lines->sum('lineTotal'), 2), // calculated by the server
            ],
        ], fn ($v) => $v !== null), $status);
    }
}
