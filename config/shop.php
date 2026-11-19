<?php


return [
    // Flat shipping fee added to every order (the frontend currently shows "Free")
    'shipping_fee' => (float) env('SHOP_SHIPPING_FEE', 0),

    // Products with stock <= this number are "low stock"
    'low_stock_threshold' => (int) env('SHOP_LOW_STOCK', 5),

    // Must match the payment options in the frontend Checkout page
    'payment_methods' => ['Cash on Delivery', 'GCash / Online Payment'],

    // First admin account (see database/seeders/AdminSeeder.php and `php artisan shop:create-admin`)
    'admin' => [
        'name' => env('ADMIN_NAME', 'Admin'),
        'email' => env('ADMIN_EMAIL'),
        'password' => env('ADMIN_PASSWORD'),
    ],

    // Optional demo data (database/seeders/DemoDataSeeder.php)
    'demo_password' => env('DEMO_PASSWORD'),
];
