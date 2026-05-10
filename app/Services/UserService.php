<?php

namespace App\Services;

use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Models\Outlet;
use App\Models\User;

class UserService
{
    public function getAll(User $authUser)
    {
        $perPage = min((int) request('per_page', 15), 100);
        $role = request('role');
        $search = request('search');

        $query = User::with(['business', 'outlet'])
            ->when($role, fn($q) => $q->where('role', $role))
            ->when($search, fn($q) => $q->where('name', 'like', "%{$search}%"));

        if ($authUser->role !== 'superadmin') {
            $query->where('business_id', $authUser->business_id);
        }

        return $query->paginate($perPage);
    }

    public function create(StoreUserRequest $request, User $authUser)
    {
        $userRole = $authUser->role;
        $businessId = $userRole === 'superadmin'
            ? $request->business_id
            : $authUser->business_id;
        if ($request->outlet_id) {
            Outlet::where('id', $request->outlet_id)
                ->where('business_id', $businessId)
                ->firstOrFail();
        }
        $data = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => $request->password,
            'role' => $request->role,
            'business_id' => $businessId,
            'outlet_id' => $request->outlet_id,
        ]);

        return $data;
    }

    public function update(UpdateUserRequest $request, User $user): User
    {
        $businessId = $user->business_id;
        if ($request->outlet_id) {
            Outlet::where('id', $request->outlet_id)
                ->where('business_id', $businessId)
                ->firstOrFail();
        }
        $data = $request->only([
            'name',
            'email',
            'role',
            'outlet_id',
            'is_active'
        ]);

        if ($request->filled('password')) {
            $data['password'] = $request->password;
        }

        $user->update($data);
        return $user;
    }

    public function delete(User $user)
    {
        $user->delete();
    }

    public function authorizeAccess(User $authUser, User $targeUser)
    {
        $userRole = $authUser->role;
        if ($userRole === 'superadmin') {
            return;
        }
        if ($authUser->business_id !== $targeUser->business_id) {
            abort(403, 'Unauthorized');
        }
    }
}