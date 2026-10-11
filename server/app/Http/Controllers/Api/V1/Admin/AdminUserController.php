<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Enums\UserStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreUserRequest;
use App\Http\Requests\Admin\UpdateUserRequest;
use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class AdminUserController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $users = User::with(['roles', 'permissions'])
            ->when($request->filled('role'), fn ($q) => $q->role($request->role))
            ->when($request->filled('search'), fn ($q) => $q->where(function ($q) use ($request) {
                $q->where('name', 'like', "%{$request->search}%")
                  ->orWhere('email', 'like', "%{$request->search}%");
            }))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->status))
            ->latest()
            ->paginate(20);

        return $this->paginated($users->through(fn ($u) => [
            'id'                 => $u->id,
            'name'               => $u->name,
            'email'              => $u->email,
            'phone'              => $u->phone,
            'status'             => $u->status,
            'roles'              => $u->getRoleNames(),
            'permissions'        => $u->getAllPermissions()->pluck('name'),
            'direct_permissions' => $u->getDirectPermissions()->pluck('name'),
            'on_duty'            => (bool) $u->on_duty,
            'section'            => $u->section,
            'loyalty_points'     => $u->loyalty_points,
            'created_at'         => $u->created_at,
        ]));
    }

    public function store(StoreUserRequest $request): JsonResponse
    {
        $data = $request->validated();

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

    public function update(UpdateUserRequest $request, User $user): JsonResponse
    {
        $data = $request->validated();

        if (isset($data['password'])) {
            $data['password'] = Hash::make($data['password']);
        }

        if (isset($data['role'])) {
            $user->syncRoles([$data['role']]);
            unset($data['role']);
        }

        if (array_key_exists('permissions', $data)) {
            $user->syncPermissions($data['permissions'] ?? []);
            unset($data['permissions']);
        }

        $user->update($data);
        ActivityLog::record('user.updated', $user, ['name' => $user->name]);

        return $this->success([
            'id'                 => $user->id,
            'name'               => $user->name,
            'email'              => $user->email,
            'roles'              => $user->getRoleNames(),
            'permissions'        => $user->getAllPermissions()->pluck('name'),
            'direct_permissions' => $user->getDirectPermissions()->pluck('name'),
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
        return $this->success(Role::with('permissions:id,name')->orderBy('name')->get(['id', 'name']));
    }

    public function permissions(): JsonResponse
    {
        return $this->success(Permission::orderBy('name')->get(['id', 'name']));
    }
}
