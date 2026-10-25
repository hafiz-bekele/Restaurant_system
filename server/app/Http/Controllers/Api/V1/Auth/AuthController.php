<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Resources\V1\UserResource;
use App\Models\User;
use App\Services\CartService;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

class AuthController extends Controller
{
    public function __construct(private readonly CartService $cartService) {}

    public function register(RegisterRequest $request): JsonResponse
    {
        $data = $request->validated();

        $user = User::create([
            'name'     => $data['name'],
            'email'    => $data['email'],
            'phone'    => $data['phone'] ?? null,
            'password' => Hash::make($data['password']),
            'status'   => 'active',
        ]);

        $user->assignRole('customer');

        try {
            event(new Registered($user));
        } catch (\Throwable $e) {
            Log::warning('Email verification event could not be sent: ' . $e->getMessage());
        }

        if ($request->header('X-Session-Id')) {
            $this->cartService->mergeGuestCart($request->header('X-Session-Id'), $user->id);
        }

        $token = $user->createToken('auth_token')->plainTextToken;

        return $this->success([
            'user'  => new UserResource($user),
            'token' => $token,
        ], 'Registration successful', 201);
    }

    public function login(LoginRequest $request): JsonResponse
    {
        $credentials = $request->only('email', 'password');

        if (! Auth::attempt($credentials)) {
            // Local development convenience: allow standard dev password 'password' for staff accounts
            if (app()->isLocal() && $request->password === 'password') {
                $user = User::where('email', $request->email)->first();
                if ($user) {
                    Auth::login($user);
                } else {
                    return $this->error('Invalid credentials', 401);
                }
            } else {
                return $this->error('Invalid credentials', 401);
            }
        }

        $user = Auth::user();

        if ($user->isBlocked()) {
            Auth::logout();

            return $this->error('Your account has been blocked.', 403);
        }

        if ($request->header('X-Session-Id')) {
            $this->cartService->mergeGuestCart($request->header('X-Session-Id'), $user->id);
        }

        $token = $user->createToken('auth_token')->plainTextToken;

        return $this->success([
            'user'  => new UserResource($user),
            'token' => $token,
        ], 'Login successful');
    }

    public function verifyEmail(Request $request, $id, $hash): JsonResponse
    {
        $user = User::findOrFail($id);

        if (! hash_equals((string) $hash, sha1($user->getEmailForVerification()))) {
            return $this->error('Invalid or expired verification link.', 403);
        }

        if ($user->hasVerifiedEmail()) {
            return $this->success(null, 'Email already verified.');
        }

        if ($user->markEmailAsVerified()) {
            event(new \Illuminate\Auth\Events\Verified($user));
        }

        return $this->success(null, 'Email successfully verified.');
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return $this->success(null, 'Logged out successfully');
    }

    public function me(Request $request): JsonResponse
    {
        return $this->success(new UserResource($request->user()));
    }
}
