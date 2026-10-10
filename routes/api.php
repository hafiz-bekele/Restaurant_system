<?php

use App\Http\Controllers\Api\V1\Admin\AdminDishController;
use App\Http\Controllers\Api\V1\Admin\AdminOrderController;
use App\Http\Controllers\Api\V1\Admin\AdminReportController;
use App\Http\Controllers\Api\V1\Admin\AdminSettingController;
use App\Http\Controllers\Api\V1\Auth\AuthController;
use App\Http\Controllers\Api\V1\Cart\CartController;
use App\Http\Controllers\Api\V1\Menu\CategoryController;
use App\Http\Controllers\Api\V1\Menu\DishController;
use App\Http\Controllers\Api\V1\Order\OrderController;
use App\Http\Controllers\Api\V1\Payment\PaymentController;
use App\Http\Controllers\Api\V1\Profile\AddressController;
use App\Http\Controllers\Api\V1\Profile\FavoriteController;
use App\Http\Controllers\Api\V1\Profile\ProfileController;
use App\Http\Controllers\Api\V1\Profile\ReviewController;
use App\Http\Controllers\Api\V1\Reservation\ReservationController;
use App\Http\Controllers\Api\V1\Staff\DeliveryController;
use App\Http\Controllers\Api\V1\Staff\KitchenController;
use App\Http\Controllers\Api\V1\Staff\WaiterController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {

    // Auth
    Route::prefix('auth')->group(function () {
        Route::post('register', [AuthController::class, 'register']);
        Route::post('login', [AuthController::class, 'login'])->middleware('throttle:10,1');
        Route::post('logout', [AuthController::class, 'logout'])->middleware('auth:sanctum');
        Route::get('me', [AuthController::class, 'me'])->middleware('auth:sanctum');
    });

    // Public menu
    Route::get('categories', [CategoryController::class, 'index']);
    Route::get('categories/{category}', [CategoryController::class, 'show']);
    Route::get('dishes/featured', [DishController::class, 'featured']);
    Route::get('dishes/{slug}', [DishController::class, 'show']);
    Route::get('dishes', [DishController::class, 'index']);
    Route::get('search', [DishController::class, 'search']);

    // Cart (no auth required — uses session_id for guests)
    Route::prefix('cart')->group(function () {
        Route::get('/', [CartController::class, 'show']);
        Route::post('items', [CartController::class, 'addItem']);
        Route::patch('items/{cartItem}', [CartController::class, 'updateItem']);
        Route::delete('items/{cartItem}', [CartController::class, 'removeItem']);
        Route::post('coupon', [CartController::class, 'applyCoupon']);
        Route::delete('coupon', [CartController::class, 'removeCoupon']);
    });

    // Reservations
    Route::get('reservations/availability', [ReservationController::class, 'availability']);
    Route::post('reservations', [ReservationController::class, 'store']);
    Route::get('reservations/{reservation}', [ReservationController::class, 'show']);
    Route::patch('reservations/{reservation}', [ReservationController::class, 'update']);
    Route::delete('reservations/{reservation}', [ReservationController::class, 'destroy']);

    // Payment webhooks (no auth — verified by signature)
    Route::post('webhooks/stripe', [PaymentController::class, 'stripeWebhook']);
    Route::post('webhooks/chapa', [PaymentController::class, 'chapaWebhook']);

    // Authenticated routes
    Route::middleware('auth:sanctum')->group(function () {

        // Orders
        Route::get('orders', [OrderController::class, 'index']);
        Route::post('orders', [OrderController::class, 'store']);
        Route::get('orders/{order}', [OrderController::class, 'show']);
        Route::post('orders/{order}/cancel', [OrderController::class, 'cancel']);
        Route::get('orders/{order}/track', [OrderController::class, 'track']);

        // Payments
        Route::post('payments/intent', [PaymentController::class, 'createIntent']);

        // Profile
        Route::get('profile', [ProfileController::class, 'show']);
        Route::put('profile', [ProfileController::class, 'update']);
        Route::apiResource('addresses', AddressController::class)->except(['show']);
        Route::get('favorites', [FavoriteController::class, 'index']);
        Route::post('favorites', [FavoriteController::class, 'store']);
        Route::delete('favorites/{dishId}', [FavoriteController::class, 'destroy']);
        Route::post('reviews', [ReviewController::class, 'store']);

        // Staff — kitchen
        Route::middleware('role:kitchen|super-admin')->group(function () {
            Route::get('kitchen/tickets', [KitchenController::class, 'tickets']);
            Route::patch('kitchen/tickets/{order}', [KitchenController::class, 'updateTicket']);
        });

        // Staff — waiter
        Route::middleware('role:waiter|super-admin')->group(function () {
            Route::get('waiter/tables', [WaiterController::class, 'tables']);
        });

        // Staff — delivery
        Route::middleware('role:delivery|super-admin')->group(function () {
            Route::get('delivery/orders', [DeliveryController::class, 'index']);
            Route::patch('delivery/orders/{order}', [DeliveryController::class, 'updateStatus']);
        });

        // Admin
        Route::middleware('role:manager|super-admin')->prefix('admin')->group(function () {
            Route::apiResource('dishes', AdminDishController::class);
            Route::get('orders', [AdminOrderController::class, 'index']);
            Route::patch('orders/{order}/status', [AdminOrderController::class, 'updateStatus']);
            Route::get('reports/sales', [AdminReportController::class, 'sales']);
            Route::get('settings', [AdminSettingController::class, 'index']);
            Route::put('settings', [AdminSettingController::class, 'update']);
        });
    });
});
