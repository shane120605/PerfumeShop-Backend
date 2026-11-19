<?php


use App\Http\Controllers\Api\Admin;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CartController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\ProductController;
use Illuminate\Support\Facades\Route;

// ---------- AUTH ----------
Route::prefix('auth')->group(function () {
    Route::middleware('throttle:login')->group(function () {
        Route::post('register', [AuthController::class, 'register']);
        Route::post('login', [AuthController::class, 'login']);
        Route::post('admin/login', [AuthController::class, 'adminLogin']);
    });

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('logout', [AuthController::class, 'logout']);
        Route::get('me', [AuthController::class, 'me']);
    });
});

// ---------- PUBLIC (no login needed): Home, Shop, Product Details ----------
Route::get('categories', [CategoryController::class, 'index']);
Route::get('categories/{category}', [CategoryController::class, 'show']);
Route::get('products', [ProductController::class, 'index']);
Route::get('products/{product}', [ProductController::class, 'show']);

// ---------- CUSTOMER (logged in as a customer): Cart, Checkout, My Orders ----------
Route::middleware(['auth:sanctum', 'role:customer'])->group(function () {
    Route::get('cart', [CartController::class, 'index']);
    Route::delete('cart', [CartController::class, 'clear']);
    Route::post('cart/items', [CartController::class, 'store']);
    Route::put('cart/items/{product}', [CartController::class, 'update']);
    Route::delete('cart/items/{product}', [CartController::class, 'destroy']);

    Route::get('orders', [OrderController::class, 'index']);
    Route::post('orders', [OrderController::class, 'store']);
    Route::get('orders/{orderNumber}', [OrderController::class, 'show'])->whereNumber('orderNumber');
    Route::post('orders/{orderNumber}/cancel', [OrderController::class, 'cancel'])->whereNumber('orderNumber');
});

// ---------- ADMIN (logged in as an admin) ----------
Route::middleware(['auth:sanctum', 'role:admin'])->prefix('admin')->group(function () {
    Route::get('dashboard', Admin\DashboardController::class);
    Route::get('reports/sales', Admin\SalesReportController::class);

    Route::apiResource('categories', Admin\CategoryController::class);

    // product list/details are the public endpoints; write actions are admin only
    Route::get('products', [ProductController::class, 'index']);
    Route::get('products/{product}', [ProductController::class, 'show']);
    Route::post('products', [Admin\ProductController::class, 'store']);
    Route::match(['put', 'patch', 'post'], 'products/{product}', [Admin\ProductController::class, 'update']);
    Route::delete('products/{product}', [Admin\ProductController::class, 'destroy']);

    Route::apiResource('users', Admin\UserController::class);

    Route::get('orders', [Admin\OrderController::class, 'index']);
    Route::get('orders/{order}', [Admin\OrderController::class, 'show']);
    Route::patch('orders/{order}/status', [Admin\OrderController::class, 'updateStatus']);
});
