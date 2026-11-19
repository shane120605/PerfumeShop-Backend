<?php


namespace App\Services;

use App\Models\CartItem;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class OrderService
{
    /**
     * @param  array<int,int>  $lines  [productId => quantity]
     * @param  array{full_name:string,address:string,city:string,phone:string}  $shipping
     */
    public function place(User $user, array $shipping, string $paymentMethod, array $lines): Order
    {
        // retry only if two customers got the same order number at the same moment
        return retry(
            3,
            fn () => DB::transaction(fn () => $this->createOrder($user, $shipping, $paymentMethod, $lines)),
            50,
            fn ($e) => $e instanceof UniqueConstraintViolationException
        );
    }

    private function createOrder(User $user, array $shipping, string $paymentMethod, array $lines): Order
    {
        ksort($lines); // always lock in the same order -> avoids deadlocks

        $products = Product::whereIn('id', array_keys($lines))
            ->orderBy('id')
            ->lockForUpdate()
            ->get()
            ->keyBy('id');

        // 1) validate stock
        $problems = [];
        foreach ($lines as $productId => $qty) {
            $product = $products->get($productId);
            if (! $product) {
                $problems[] = "Product #{$productId} is no longer available.";
            } elseif ($product->stock < $qty) {
                $problems[] = $product->stock > 0
                    ? "Only {$product->stock} of {$product->name} left in stock."
                    : "{$product->name} is out of stock.";
            }
        }
        if ($problems) {
            throw ValidationException::withMessages(['items' => $problems]);
        }

        // 2) totals - calculated in cents to avoid rounding errors
        $subtotalCents = 0;
        $rows = [];
        foreach ($lines as $productId => $qty) {
            $product = $products[$productId];
            $unitCents = (int) round(((float) $product->price) * 100);
            $lineCents = $unitCents * $qty;
            $subtotalCents += $lineCents;
            $rows[] = compact('product', 'qty', 'unitCents', 'lineCents');
        }
        $shippingCents = (int) round(((float) config('shop.shipping_fee')) * 100);

        // 3) create the order
        $order = new Order;
        $order->fill([
            'order_number' => ((int) Order::max('order_number') ?: 12334) + 1,
            'user_id' => $user->id,
            'customer_name' => $shipping['full_name'],
            'customer_email' => $user->email,
            'phone' => $shipping['phone'],
            'shipping_address' => $shipping['address'],
            'city' => $shipping['city'],
            'payment_method' => $paymentMethod,
            'status' => Order::STATUS_PENDING,
            'subtotal' => $this->money($subtotalCents),
            'shipping_fee' => $this->money($shippingCents),
            'total' => $this->money($subtotalCents + $shippingCents),
        ])->save();

        // 4) items (name + price are copied) and stock
        foreach ($rows as $row) {
            $order->items()->create([
                'product_id' => $row['product']->id,
                'product_name' => $row['product']->name,
                'unit_price' => $this->money($row['unitCents']),
                'quantity' => $row['qty'],
                'line_total' => $this->money($row['lineCents']),
            ]);
            $row['product']->decrement('stock', $row['qty']);
        }

        // 5) remove the bought products from the server cart
        CartItem::where('user_id', $user->id)->whereIn('product_id', array_keys($lines))->delete();

        return $order->load('items');
    }

    /**
     * Allowed changes: pending -> delivered, pending -> cancelled.
     * Delivered and cancelled orders are final.
     */
    public function changeStatus(Order $order, string $newStatus): Order
    {
        return DB::transaction(function () use ($order, $newStatus) {
            $order = Order::whereKey($order->id)->lockForUpdate()->firstOrFail();

            if ($order->status === $newStatus) {
                return $order->load('items');
            }

            if ($order->status !== Order::STATUS_PENDING) {
                throw ValidationException::withMessages([
                    'status' => ["A {$order->status} order can no longer be changed."],
                ]);
            }

            if ($newStatus === Order::STATUS_CANCELLED) {
                $this->restoreStock($order);
            }

            $order->update(['status' => $newStatus]);

            return $order->load('items');
        });
    }

    private function restoreStock(Order $order): void
    {
        $items = $order->items()->whereNotNull('product_id')->orderBy('product_id')->get();

        foreach ($items as $item) {
            Product::whereKey($item->product_id)->lockForUpdate()->first()?->increment('stock', $item->quantity);
        }
    }

    private function money(int $cents): string
    {
        return number_format($cents / 100, 2, '.', '');
    }
}
