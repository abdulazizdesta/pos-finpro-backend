<?php

namespace App\Services;

use App\Http\Requests\StoreOutletRequest;
use App\Http\Requests\UpdateOutletRequest;
use App\Models\Outlet;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class OutletService
{
    public function getAll(User $authUser): LengthAwarePaginator
    {
        $perPage = min((int) request('per_page', 15), 100);

        return Outlet::where('business_id', $authUser->business_id)
            ->when(request('is_active') !== null, fn($q) => $q->where('is_active', request('is_active')))
            ->latest()
            ->paginate($perPage);
    }

    public function getDetail(Outlet $outlet, User $authUser): Outlet
    {
        $this->authorize($outlet, $authUser);
        return $outlet;
    }

    public function create(StoreOutletRequest $request, User $authUser): Outlet
    {
        return Outlet::create([
            'business_id' => $authUser->business_id,
            'name'        => $request->name,
            'code'        => strtoupper($request->code),
            'phone'       => $request->phone,
            'address'     => $request->address,
            'is_active'   => $request->is_active ?? true,
        ]);
    }

    public function update(UpdateOutletRequest $request, Outlet $outlet, User $authUser): Outlet
    {
        $this->authorize($outlet, $authUser);

        $data = $request->only(['name', 'code', 'phone', 'address', 'is_active']);

        if (isset($data['code'])) {
            $data['code'] = strtoupper($data['code']);
        }

        $outlet->update($data);

        return $outlet->fresh();
    }

    public function delete(Outlet $outlet, User $authUser): void
    {
        $this->authorize($outlet, $authUser);

        $hasActiveShift = $outlet->shifts()->where('status', 'open')->exists();
        if ($hasActiveShift) {
            abort(422, 'Cannot delete outlet with active shift');
        }

        $outlet->delete();
    }

    private function authorize(Outlet $outlet, User $authUser): void
    {
        if ((int) $outlet->business_id !== (int) $authUser->business_id) {
            abort(403, 'Unauthorized access to this outlet');
        }
    }
}
