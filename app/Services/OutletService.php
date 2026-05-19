<?php

namespace App\Services;

use App\Http\Requests\StoreOutletRequest;
use App\Http\Requests\UpdateOutletRequest;
use App\Models\Outlet;
use App\Models\Product;
use App\Models\Stock;
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

        $code = $request->code
            ? strtoupper($request->code)
            : $this->generateCode($request->name);

        $outlet = Outlet::create([
            'business_id' => $authUser->business_id,
            'name' => $request->name,
            'code' => $code,
            'phone' => $request->phone,
            'address' => $request->address,
            'is_active' => $request->is_active ?? true,
        ]);

        $products = Product::where('business_id', $authUser->business_id)
            ->where('is_active', true)
            ->get();

        foreach ($products as $product) {
            Stock::firstOrCreate([
                'product_id' => $product->id,
                'outlet_id' => $outlet->id,
                'variant_id' => 0,
            ], ['quantity' => 0, 'min_threshold' => 5]);
        }

        return $outlet;
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

    private function generateCode(string $name): string
    {
        $words = explode(' ', $name);
        $code = strtoupper(implode('', array_map(fn($w) => substr($w, 0, 2), $words)));
        $code = substr($code, 0, 6);
        $original = $code;
        $i = 1;
        while (Outlet::where('code', $code)->exists()) {
            $code = $original . $i;
            $i++;
        }

        return $code;
    }
}
