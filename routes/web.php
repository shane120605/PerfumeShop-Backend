<?php

use Illuminate\Support\Facades\Route;

Route::get('/', fn () => response()->json([
    'status' => 'Perfume Shop API is running',
]));