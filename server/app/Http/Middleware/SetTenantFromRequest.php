<?php

namespace App\Http\Middleware;

use App\Models\Hotel;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * REQ-1.2 / REQ-1.3: Resolve the current hotel tenant from the request
 * and bind it into the service container.
 *
 * Resolution priority:
 *  1. X-Hotel-ID header (staff / admin API clients)
 *  2. hotel_id query parameter (guest QR sessions)
 *  3. Authenticated user's own hotel_id
 *
 * Cross-tenant access is blocked with 403 (REQ-1.3).
 */
class SetTenantFromRequest
{
    public function handle(Request $request, Closure $next): Response
    {
        $hotelId = $request->header('X-Hotel-ID')
            ?? $request->query('hotel_id')
            ?? $request->user()?->hotel_id;

        // Bind into container so TenantScope can read it
        $resolvedId = $hotelId ? (int) $hotelId : null;
        app()->bind('current_hotel_id', fn () => $resolvedId);

        // If an authenticated user belongs to a different hotel than requested, block them
        $user = $request->user();

        if ($user && $user->hotel_id && $hotelId && (int) $user->hotel_id !== (int) $hotelId) {
            activity_log_security('cross_tenant_access_attempt', $user, ['requested_hotel_id' => $hotelId]);

            return response()->json([
                'data'    => null,
                'message' => 'Access forbidden: cross-tenant access denied.',
                'errors'  => null,
            ], 403);
        }

        return $next($request);
    }
}
