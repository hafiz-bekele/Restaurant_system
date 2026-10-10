<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Enums\UserStatus;
use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class AdminUserController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $users = User::with('roles')
            ->when($request->filled('role'), fn ($q) => $q->role($request->role))
            ->when($request->filled('search'), fn ($q) => $q->where(function ($q) use ($request) {
                $q->where('name', 'like', "%{$request->search}%")
                  ->orWhere('email', 'like', "%{$request->search}%");
            }))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->status))
            ->latest()
            ->paginate(20);

        return $this->paginated($users->through(fn ($u) => [
            'id'             => $u->id,
            'name'           => $u->name,
            'email'          => $u->email,
            'phone'          => $u->phone,
            'status'         => $u->status,
            'roles'          => $u->getRoleNames(),
            'loyalty_points' => $u->loyalty_points,
            'created_at'     => $u->created_at,
        ]));
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name'     => 'required|string|max:255',
            'email'    => 'required|email|unique:users,email',
            'phone'    => 'nullable|string|max:20',
            'password' => 'required|string|min:8',
            'role'     => 'required|string|exists:roles,name',
        ]);

        $user = User::create([
            'name'     => $data['name'],
            'email'    => $data['email'],
            'phone'    => $data['phone'] ?? null,
            'password' => Hash::make($data['password']),
            'status'   => UserStatus::Active,
        ]);

        $user->assignRole($data['role']);
        ActivityLog::record('user.created', $user, ['name' => $user->name, 'role' => $data['role']]);

        return $this->success([
            'id'    => $user->id,
            'name'  => $user->name,
            'email' => $user->email,
            'roles' => $user->getRoleNames(),
        ], 'User created', 201);
    }

    public function show(User $user): JsonResponse
    {
        return $this->success([
            'id'             => $user->id,
            'name'           => $user->name,
            'email'          => $user->email,
            'phone'          => $user->phone,
            'status'         => $user->status,
            'roles'          => $user->getRoleNames(),
            'loyalty_points' => $user->loyalty_points,
            'created_at'     => $user->created_at,
        ]);
    }

    public function update(Request $request, User $user): JsonResponse
    {
        $data = $request->validate([
            'name'     => 'sometimes|string|max:255',
            'phone'    => 'nullable|string|max:20',
            'status'   => 'sometimes|in:active,inactive,blocked',
            'role'     => 'sometimes|string|exists:roles,name',
            'password' => 'sometimes|string|min:8',
        ]);

        if (isset($data['password'])) {
            $data['password'] = Hash::make($data['password']);
        }

        if (isset($data['role'])) {
            $user->syncRoles([$data['role']]);
            unset($data['role']);
        }

        $user->update($data);
        ActivityLog::record('user.updated', $user, ['name' => $user->name]);

        return $this->success([
            'id'    => $user->id,
            'name'  => $user->name,
            'email' => $user->email,
            'roles' => $user->getRoleNames(),
        ], 'User updated');
    }

    public function destroy(User $user): JsonResponse
    {
        ActivityLog::record('user.deleted', $user, ['name' => $user->name]);
        $user->delete();

        return $this->success(null, 'User deleted');
    }

    public function roles(): JsonResponse
    {
        $roles = Role::orderBy('name')->get(['id', 'name']);

        return $this->success($roles);
    }
}
