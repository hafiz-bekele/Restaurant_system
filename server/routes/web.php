<?php

use Illuminate\Support\Facades\Route;

// Serve the Vue SPA for every non-API route so that Vue Router handles navigation.
Route::get('/{any?}', function () {
    return view('welcome');
})->where('any', '^(?!api).*');
