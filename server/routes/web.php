<?php

use Illuminate\Support\Facades\Route;

// Explicit named login route so that Laravel route('login') always resolves cleanly.
Route::get('/login', function () {
    return view('welcome');
})->name('login');

// Serve the Vue SPA for every non-API route so that Vue Router handles navigation.
Route::get('/{any?}', function () {
    return view('welcome');
})->where('any', '^(?!api).*');
