<?php

use App\Http\Controllers\Api\V1\Admin\AdminAuditController;
use App\Http\Controllers\Api\V1\Admin\AdminBannerController;
use App\Http\Controllers\Api\V1\Admin\AdminComplaintController;
use App\Http\Controllers\Api\V1\Admin\AdminCouponController;
use App\Http\Controllers\Api\V1\Admin\AdminDeliveryZoneController;
use App\Http\Controllers\Api\V1\Admin\AdminDishController;
use App\Http\Controllers\Api\V1\Admin\AdminHotelController;
use App\Http\Controllers\Api\V1\Admin\AdminInventoryController;
use App\Http\Controllers\Api\V1\Admin\AdminOrderController;
use App\Http\Controllers\Api\V1\Admin\AdminReportController;
use App\Http\Controllers\Api\V1\Admin\AdminReservationController;
use App\Http\Controllers\Api\V1\Admin\AdminReviewController;
use App\Http\Controllers\Api\V1\Admin\AdminRoomController;
use App\Http\Controllers\Api\V1\Admin\AdminSettingController;
use App\Http\Controllers\Api\V1\Admin\AdminTableController;
use App\Http\Controllers\Api\V1\Admin\AdminUserController;
use App\Http\Controllers\Api\V1\Auth\AuthController;
use App\Http\Controllers\Api\V1\Cart\CartController;
use App\Http\Controllers\Api\V1\Guest\ComplaintController;
use App\Http\Controllers\Api\V1\Guest\GuestQrController;
use App\Http\Controllers\Api\V1\Guest\RoomBookingController;
use App\Http\Controllers\Api\V1\Menu\CategoryController;
use App\Http\Controllers\Api\V1\Menu\DishController;
use App\Http\Controllers\Api\V1\Order\OrderController;
use App\Http\Controllers\Api\V1\Payment\PaymentController;
use App\Http\Controllers\Api\V1\Profile\AddressController;
use App\Http\Controllers\Api\V1\Profile\FavoriteController;
use App\Http\Controllers\Api\V1\Profile\ProfileController;
use App\Http\Controllers\Api\V1\Profile\ReviewController;
use App\Http\Controllers\Api\V1\Reservation\ReservationController;
use App\Http\Controllers\Api\V1\Staff\CashierController;
use App\Http\Controllers\Api\V1\Staff\DeliveryController;
use App\Http\Controllers\Api\V1\Staff\KitchenController;
use App\Http\Controllers\Api\V1\Staff\ReceptionController;
use App\Http\Controllers\Api\V1\Staff\WaiterController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {

    // ── Auth ─────────────────────────────────────────────────────────────────
    Route::prefix('auth')->group(function () {
        Route::post('register', [AuthController::class, 'register']);
        Route::post('login', [AuthController::class, 'login'])->middleware('throttle:10,1');
        Route::post('logout', [AuthController::class, 'logout'])->middleware('auth:sanctum');
        Route::get('me', [AuthController::class, 'me'])->middleware('auth:sanctum');
    });

    // ── Public menu ───────────────────────────────────────────────────────────
    Route::get('categories', [CategoryController::class, 'index']);
    Route::get('categories/{category}', [CategoryController::class, 'show']);
    Route::get('dishes/featured', [DishController::class, 'featured']);
    Route::get('dishes/{slug}', [DishController::class, 'show']);
    Route::get('dishes/{dish}/reviews', [ReviewController::class, 'dishReviews']);
    Route::get('dishes', [DishController::class, 'index']);
    Route::get('search', [DishController::class, 'search']);

    // ── Public banners & delivery zones ───────────────────────────────────────
    Route::get('banners', [AdminBannerController::class, 'index']);
    Route::get('delivery-zones', [AdminDeliveryZoneController::class, 'index']);

    // ── Guest QR resolution (REQ-3.2) ─────────────────────────────────────────
    Route::get('guest/resolve-qr/{token}', [GuestQrController::class, 'resolve']);

    // ── Room browsing + booking (REQ-4.1 – 4.4) ───────────────────────────────
    Route::get('rooms/available', [RoomBookingController::class, 'available']);
    Route::post('rooms/book', [RoomBookingController::class, 'store']);
    Route::get('rooms/bookings/{roomBooking}', [RoomBookingController::class, 'show']);

    // ── Complaint tickets (REQ-11.2) ──────────────────────────────────────────
    Route::post('complaints', [ComplaintController::class, 'store']);
    Route::get('complaints/{ticketNumber}', [ComplaintController::class, 'show']);

    // ── Cart (session-based — no auth required) ───────────────────────────────
    Route::prefix('cart')->group(function () {
        Route::get('/', [CartController::class, 'show']);
        Route::post('items', [CartController::class, 'addItem']);
        Route::patch('items/{cartItem}', [CartController::class, 'updateItem']);
        Route::delete('items/{cartItem}', [CartController::class, 'removeItem']);
        Route::post('coupon', [CartController::class, 'applyCoupon']);
        Route::delete('coupon', [CartController::class, 'removeCoupon']);
    });

    // ── Table reservations (public) ────────────────────────────────────────────
    Route::get('reservations/availability', [ReservationController::class, 'availability']);
    Route::post('reservations', [ReservationController::class, 'store']);
    Route::get('reservations/{reservation}', [ReservationController::class, 'show']);
    Route::patch('reservations/{reservation}', [ReservationController::class, 'update']);
    Route::delete('reservations/{reservation}', [ReservationController::class, 'destroy']);

    // ── Payment webhooks & Public verification ──────────────────────────────
    Route::post('webhooks/stripe', [PaymentController::class, 'stripeWebhook']);
    Route::post('webhooks/chapa', [PaymentController::class, 'chapaWebhook']);
    Route::post('payments/chapa/verify', [PaymentController::class, 'verifyChapa']);

    // ── Orders & Payments (Public / Guest Accessible — no login/register required) ──
    Route::post('orders', [OrderController::class, 'store']);
    Route::get('orders/{order}', [OrderController::class, 'show']);
    Route::get('orders/{order}/track', [OrderController::class, 'track']);
    Route::post('payments/intent', [PaymentController::class, 'createIntent']);
    Route::post('payments/chapa', [PaymentController::class, 'createChapaIntent']);
    Route::post('payments/room-charge', [PaymentController::class, 'roomCharge']);

    // ── Authenticated routes ───────────────────────────────────────────────────
    Route::middleware('auth:sanctum')->group(function () {

        // Orders (customer order history & cancellation)
        Route::get('orders', [OrderController::class, 'index']);
        Route::post('orders/{order}/cancel', [OrderController::class, 'cancel']);

        // Profile
        Route::get('profile', [ProfileController::class, 'show']);
        Route::put('profile', [ProfileController::class, 'update']);
        Route::apiResource('addresses', AddressController::class)->except(['show']);
        Route::get('favorites', [FavoriteController::class, 'index']);
        Route::post('favorites', [FavoriteController::class, 'store']);
        Route::delete('favorites/{dishId}', [FavoriteController::class, 'destroy']);
        Route::get('reviews/my', [ReviewController::class, 'myReviews']);
        Route::post('reviews', [ReviewController::class, 'store']);

        // Staff — kitchen (REQ-8.x)
        Route::middleware('role:kitchen|manager|admin|super-admin')->group(function () {
            Route::get('kitchen/tickets', [KitchenController::class, 'tickets']);
            Route::patch('kitchen/tickets/{order}', [KitchenController::class, 'updateTicket']);
        });

        // Staff — waiter (REQ-7.x, REQ-8.3)
        Route::middleware('role:waiter|manager|admin|super-admin')->group(function () {
            Route::get('waiter/tables', [WaiterController::class, 'tables']);
            Route::get('waiter/orders', [WaiterController::class, 'orders']);
            Route::post('waiter/orders', [WaiterController::class, 'createOrder']);
            Route::post('waiter/orders/{order}/served', [WaiterController::class, 'markServed']);
        });

        // Staff — cashier / POS billing (REQ-9.x)
        Route::middleware('role:cashier|manager|admin|super-admin')->group(function () {
            Route::get('cashier/orders', [CashierController::class, 'orders']);
            Route::post('cashier/orders/{order}/confirm-payment', [CashierController::class, 'confirmPayment']);
            Route::get('cashier/summary', [CashierController::class, 'summary']);
        });

        // Staff — delivery
        Route::middleware('role:delivery|manager|admin|super-admin')->group(function () {
            Route::get('delivery/orders', [DeliveryController::class, 'index']);
            Route::patch('delivery/orders/{order}', [DeliveryController::class, 'updateStatus']);
        });

        // Staff — reception (REQ-10.x)
        Route::middleware('role:reception|waiter|manager|admin|super-admin')->prefix('reception')->group(function () {
            Route::get('dashboard', [ReceptionController::class, 'dashboard']);
            Route::get('tables/available', [ReceptionController::class, 'availableTables']);
            Route::post('walk-in', [ReceptionController::class, 'walkIn']);
            Route::post('reservations/{reservation}/check-in', [ReceptionController::class, 'checkIn']);
            Route::post('reservations/{reservation}/no-show', [ReceptionController::class, 'noShow']);
            Route::post('tables/{table}/release', [ReceptionController::class, 'releaseTable']);
        });

        // ── Admin ──────────────────────────────────────────────────────────────
        Route::middleware('role:admin|manager|super-admin')->prefix('admin')->group(function () {

            // Dishes
            Route::apiResource('dishes', AdminDishController::class);

            // Reviews Moderation
            Route::get('reviews', [AdminReviewController::class, 'index']);
            Route::patch('reviews/{review}', [AdminReviewController::class, 'update']);
            Route::delete('reviews/{review}', [AdminReviewController::class, 'destroy']);

            // Orders
            Route::get('orders', [AdminOrderController::class, 'index']);
            Route::patch('orders/{order}/status', [AdminOrderController::class, 'updateStatus']);
            Route::post('orders/{order}/reassign-waiter', [AdminOrderController::class, 'reassignWaiter']);

            // Hotels (Multi-Tenant Management)
            Route::apiResource('hotels', AdminHotelController::class);

            // Reports
            Route::get('reports/sales', [AdminReportController::class, 'sales']);

            // Settings
            Route::get('settings', [AdminSettingController::class, 'index']);
            Route::put('settings', [AdminSettingController::class, 'update']);

            // Coupons
            Route::get('coupons', [AdminCouponController::class, 'index']);
            Route::post('coupons', [AdminCouponController::class, 'store']);
            Route::patch('coupons/{coupon}', [AdminCouponController::class, 'update']);
            Route::delete('coupons/{coupon}', [AdminCouponController::class, 'destroy']);

            // Users & RBAC Permissions — static routes BEFORE {user} wildcard
            Route::get('users/roles', [AdminUserController::class, 'roles']);
            Route::get('users/permissions', [AdminUserController::class, 'permissions']);
            Route::get('users', [AdminUserController::class, 'index']);
            Route::post('users', [AdminUserController::class, 'store']);
            Route::get('users/{user}', [AdminUserController::class, 'show']);
            Route::patch('users/{user}', [AdminUserController::class, 'update']);
            Route::patch('users/{user}/duty', [AdminUserController::class, 'toggleDuty']);
            Route::delete('users/{user}', [AdminUserController::class, 'destroy']);

            // Tables — static segments BEFORE {table} wildcard
            Route::get('tables/areas', [AdminTableController::class, 'areas']);
            Route::get('tables/menu-qr', [AdminTableController::class, 'menuQrSvg']);
            Route::get('tables', [AdminTableController::class, 'index']);
            Route::post('tables', [AdminTableController::class, 'store']);
            Route::patch('tables/{table}', [AdminTableController::class, 'update']);
            Route::delete('tables/{table}', [AdminTableController::class, 'destroy']);
            Route::post('tables/{table}/regenerate-qr', [AdminTableController::class, 'regenerateQr']);
            Route::get('tables/{table}/qr', [AdminTableController::class, 'qrSvg']);

            // Rooms + bookings
            Route::get('rooms', [AdminRoomController::class, 'index']);
            Route::post('rooms', [AdminRoomController::class, 'store']);
            Route::patch('rooms/{room}', [AdminRoomController::class, 'update']);
            Route::delete('rooms/{room}', [AdminRoomController::class, 'destroy']);
            Route::get('room-bookings', [AdminRoomController::class, 'bookings']);
            Route::patch('room-bookings/{roomBooking}', [AdminRoomController::class, 'updateBooking']);

            // Reservations — static 'today' BEFORE {reservation} wildcard
            Route::get('reservations/today', [AdminReservationController::class, 'today']);
            Route::get('reservations', [AdminReservationController::class, 'index']);
            Route::get('reservations/{reservation}', [AdminReservationController::class, 'show']);
            Route::patch('reservations/{reservation}', [AdminReservationController::class, 'update']);
            Route::delete('reservations/{reservation}', [AdminReservationController::class, 'destroy']);

            // Complaints
            Route::get('complaints', [AdminComplaintController::class, 'index']);
            Route::get('complaints/{complaintTicket}', [AdminComplaintController::class, 'show']);
            Route::patch('complaints/{complaintTicket}', [AdminComplaintController::class, 'update']);

            // Delivery zones
            Route::get('delivery-zones', [AdminDeliveryZoneController::class, 'index']);
            Route::post('delivery-zones', [AdminDeliveryZoneController::class, 'store']);
            Route::patch('delivery-zones/{deliveryZone}', [AdminDeliveryZoneController::class, 'update']);
            Route::delete('delivery-zones/{deliveryZone}', [AdminDeliveryZoneController::class, 'destroy']);

            // Banners
            Route::post('banners', [AdminBannerController::class, 'store']);
            Route::patch('banners/{banner}', [AdminBannerController::class, 'update']);
            Route::delete('banners/{banner}', [AdminBannerController::class, 'destroy']);

            // Inventory — ingredients
            Route::get('inventory/ingredients', [AdminInventoryController::class, 'ingredients']);
            Route::post('inventory/ingredients', [AdminInventoryController::class, 'storeIngredient']);
            Route::patch('inventory/ingredients/{ingredient}', [AdminInventoryController::class, 'updateIngredient']);
            Route::post('inventory/ingredients/{ingredient}/adjust', [AdminInventoryController::class, 'adjustStock']);
            Route::get('inventory/low-stock', [AdminInventoryController::class, 'lowStock']);

            // Inventory — suppliers
            Route::get('inventory/suppliers', [AdminInventoryController::class, 'suppliers']);
            Route::post('inventory/suppliers', [AdminInventoryController::class, 'storeSupplier']);

            // Audit log
            Route::get('audit', [AdminAuditController::class, 'index']);
        });
    });
});
