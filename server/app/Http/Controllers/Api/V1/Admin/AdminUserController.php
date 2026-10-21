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
            ->withCount([
                'waiterAssignments as active_waiter_orders_count' => function ($q) {
                    $q->where('status', 'assigned');
                }
            ])
            ->when($request->filled('role'), fn ($q) => $q->role($request->role))
            ->when($request->filled('search'), fn ($q) => $q->where(function ($q) use ($request) {
                $q->where('name', 'like', "%{$request->search}%")
                  ->orWhere('email', 'like', "%{$request->search}%")
                  ->orWhere('phone', 'like', "%{$request->search}%")
                  ->orWhere('section', 'like', "%{$request->search}%");
            }))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->status))
            ->when($request->filled('on_duty'), fn ($q) => $q->where('on_duty', filter_var($request->on_duty, FILTER_VALIDATE_BOOLEAN)))
            ->when($request->filled('section'), fn ($q) => $q->where('section', 'like', "%{$request->section}%"))
            ->latest()
            ->paginate(30);

        return $this->paginated($users->through(fn ($u) => [
            'id'                  => $u->id,
            'hotel_id'            => $u->hotel_id,
            'name'                => $u->name,
            'email'               => $u->email,
            'phone'               => $u->phone,
            'status'              => $u->status,
            'roles'               => $u->getRoleNames(),
            'permissions'         => $u->getAllPermissions()->pluck('name'),
            'direct_permissions'  => $u->getDirectPermissions()->pluck('name'),
            'on_duty'             => (bool) $u->on_duty,
            'section'             => $u->section,
            'active_orders_count' => (int) ($u->active_waiter_orders_count ?? 0),
            'loyalty_points'      => $u->loyalty_points,
            'created_at'          => $u->created_at,
        ]));
    }

    public function store(StoreUserRequest $request): JsonResponse
    {
        $data = $request->validated();
        $authUser = $request->user();
        $hotelId = $data['hotel_id'] ?? $authUser?->hotel_id ?? 1;

        $user = User::create([
            'name'     => $data['name'],
            'email'    => $data['email'],
            'phone'    => $data['phone'] ?? null,
            'password' => Hash::make($data['password']),
            'status'   => UserStatus::Active,
            'hotel_id' => $hotelId,
            'section'  => $data['section'] ?? null,
            'on_duty'  => isset($data['on_duty']) ? (bool) $data['on_duty'] : false,
        ]);

        $user->assignRole($data['role']);

        if (!empty($data['permissions'])) {
            $user->syncPermissions($data['permissions']);
        }

        ActivityLog::record('user.created', $user, [
            'name'    => $user->name,
            'role'    => $data['role'],
            'section' => $user->section,
            'on_duty' => (bool) $user->on_duty,
        ]);

        return $this->success([
            'id'                 => $user->id,
            'hotel_id'           => $user->hotel_id,
            'name'               => $user->name,
            'email'              => $user->email,
            'roles'              => $user->getRoleNames(),
            'section'            => $user->section,
            'on_duty'            => (bool) $user->on_duty,
            'permissions'        => $user->getAllPermissions()->pluck('name'),
            'direct_permissions' => $user->getDirectPermissions()->pluck('name'),
        ], 'Staff account onboarded and role assigned successfully', 201);
    }

    public function toggleDuty(User $user): JsonResponse
    {
        $user->update(['on_duty' => ! $user->on_duty]);

        ActivityLog::record('user.duty_toggled', $user, [
            'name'    => $user->name,
            'on_duty' => (bool) $user->on_duty,
            'section' => $user->section,
        ]);

        return $this->success([
            'id'      => $user->id,
            'name'    => $user->name,
            'on_duty' => (bool) $user->on_duty,
            'section' => $user->section,
        ], "Duty status changed to " . ($user->on_duty ? 'ON DUTY' : 'OFF SHIFT'));
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

    public function rosterStats(Request $request): JsonResponse
    {
        $users = User::with('roles')->get();

        $totalStaff = $users->count();
        $onDutyStaff = $users->where('on_duty', true)->count();

        // Roles breakdown
        $roleStats = [
            'waiter'    => ['total' => 0, 'on_duty' => 0, 'active_orders' => 0],
            'kitchen'   => ['total' => 0, 'on_duty' => 0, 'pending_tickets' => 0],
            'cashier'   => ['total' => 0, 'on_duty' => 0, 'active_shifts' => 0],
            'manager'   => ['total' => 0, 'on_duty' => 0],
            'reception' => ['total' => 0, 'on_duty' => 0],
            'delivery'  => ['total' => 0, 'on_duty' => 0],
        ];

        foreach ($users as $user) {
            foreach ($user->roles as $role) {
                if (isset($roleStats[$role->name])) {
                    $roleStats[$role->name]['total']++;
                    if ($user->on_duty) {
                        $roleStats[$role->name]['on_duty']++;
                    }
                }
            }
        }

        // Active orders for waiters & kitchen
        $activeOrders = \App\Models\Order::whereNotIn('status', [
            \App\Enums\OrderStatus::Delivered,
            \App\Enums\OrderStatus::Cancelled,
        ])->count();

        $kitchenTickets = \App\Models\Order::whereIn('status', [
            \App\Enums\OrderStatus::Confirmed,
            \App\Enums\OrderStatus::Preparing,
        ])->count();

        $activeShifts = \App\Models\CashierShift::whereNull('closed_at')->count();

        $roleStats['waiter']['active_orders'] = $activeOrders;
        $roleStats['kitchen']['pending_tickets'] = $kitchenTickets;
        $roleStats['cashier']['active_shifts'] = $activeShifts;

        // Dining areas from RestaurantTable
        $areas = \App\Models\RestaurantTable::where('is_active', true)
            ->distinct()
            ->pluck('area')
            ->filter()
            ->values()
            ->toArray();

        if (empty($areas)) {
            $areas = ['Indoor Main', 'Garden Patio', 'Sky Terrace', 'VIP Lounge'];
        }

        // Check which areas have on_duty waiters assigned
        $onDutyWaiterSections = $users->filter(fn ($u) => $u->on_duty && $u->hasRole('waiter'))
            ->pluck('section')
            ->filter()
            ->toArray();

        $coverageAlerts = [];
        foreach ($areas as $area) {
            $covered = false;
            foreach ($onDutyWaiterSections as $sec) {
                if (stripos($sec, $area) !== false) {
                    $covered = true;
                    break;
                }
            }
            if (!$covered) {
                $coverageAlerts[] = [
                    'type'    => 'area_unassigned',
                    'area'    => $area,
                    'message' => "Dining area '{$area}' currently has 0 on-duty waiters assigned.",
                ];
            }
        }

        if ($kitchenTickets > 0 && $roleStats['kitchen']['on_duty'] === 0) {
            $coverageAlerts[] = [
                'type'    => 'kitchen_unmanned',
                'message' => "Kitchen has {$kitchenTickets} pending food orders, but 0 kitchen cooks are clocked in!",
            ];
        }

        if ($roleStats['cashier']['on_duty'] === 0) {
            $coverageAlerts[] = [
                'type'    => 'cashier_closed',
                'message' => "No cashiers are currently clocked on duty at POS.",
            ];
        }

        return $this->success([
            'total_staff'      => $totalStaff,
            'on_duty_count'    => $onDutyStaff,
            'off_duty_count'   => $totalStaff - $onDutyStaff,
            'roles'            => $roleStats,
            'areas'            => $areas,
            'coverage_alerts'  => $coverageAlerts,
        ]);
    }

    public function bulkDuty(Request $request): JsonResponse
    {
        $data = $request->validate([
            'role'       => ['nullable', 'string', 'exists:roles,name'],
            'user_ids'   => ['nullable', 'array'],
            'user_ids.*' => ['integer', 'exists:users,id'],
            'on_duty'    => ['required', 'boolean'],
        ]);

        $query = User::query();

        if (!empty($data['role'])) {
            $query->role($data['role']);
        }

        if (!empty($data['user_ids'])) {
            $query->whereIn('id', $data['user_ids']);
        }

        $count = $query->update(['on_duty' => $data['on_duty']]);

        ActivityLog::record('user.bulk_duty_updated', null, [
            'role'     => $data['role'] ?? 'selected_users',
            'count'    => $count,
            'on_duty'  => (bool) $data['on_duty'],
        ]);

        return $this->success([
            'affected_count' => $count,
            'on_duty'        => (bool) $data['on_duty'],
        ], "Updated duty status for {$count} staff members.");
    }

    public function assignSection(Request $request, User $user): JsonResponse
    {
        $data = $request->validate([
            'section' => ['required', 'string', 'max:150'],
            'on_duty' => ['nullable', 'boolean'],
        ]);

        $user->section = $data['section'];
        if (isset($data['on_duty'])) {
            $user->on_duty = (bool) $data['on_duty'];
        }
        $user->save();

        ActivityLog::record('user.section_assigned', $user, [
            'name'    => $user->name,
            'section' => $user->section,
            'on_duty' => (bool) $user->on_duty,
        ]);

        return $this->success([
            'id'      => $user->id,
            'name'    => $user->name,
            'section' => $user->section,
            'on_duty' => (bool) $user->on_duty,
        ], "Assigned {$user->name} to {$user->section}");
    }
}
