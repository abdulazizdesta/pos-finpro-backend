<?php

namespace App\Services;

use App\Http\Requests\StoreDiscountRequest;
use App\Http\Requests\UpdateDiscountRequest;
use App\Models\Discount;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class DiscountService
{
    public function getAll(User $authUser): LengthAwarePaginator
    {
        $perPage = min((int) request('per_page', 15), 100);

        return Discount::where('business_id', $authUser->business_id)
            ->when(request('is_active') !== null, fn($q) => $q->where('is_active', request('is_active')))
            ->when(request('type'), fn($q) => $q->where('type', request('type')))
            ->latest('created_at')
            ->paginate($perPage);
    }

    public function getDetail(Discount $discount, User $authUser): Discount
    {
        $this->authorize($discount, $authUser);
        return $discount;
    }

    public function create(StoreDiscountRequest $request, User $authUser): Discount
    {
        return Discount::create([
            'business_id'  => $authUser->business_id,
            'code'         => strtoupper($request->code),
            'name'         => $request->name,
            'type'         => $request->type,
            'value'        => $request->value,
            'min_purchase' => $request->min_purchase ?? 0,
            'max_uses'     => $request->max_uses,
            'used_count'   => 0,
            'valid_from'   => $request->valid_from,
            'valid_until'  => $request->valid_until,
            'is_active'    => $request->is_active ?? true,
        ]);
    }

    public function update(UpdateDiscountRequest $request, Discount $discount, User $authUser): Discount
    {
        $this->authorize($discount, $authUser);

        $data = $request->only([
            'code', 'name', 'type', 'value', 'min_purchase',
            'max_uses', 'valid_from', 'valid_until', 'is_active',
        ]);

        if (isset($data['code'])) {
            $data['code'] = strtoupper($data['code']);
        }

        $discount->update($data);

        return $discount->fresh();
    }

    public function delete(Discount $discount, User $authUser): void
    {
        $this->authorize($discount, $authUser);

        if ($discount->used_count > 0) {
            abort(422, 'Cannot delete discount that has been used');
        }

        $discount->delete();
    }

    private function authorize(Discount $discount, User $authUser): void
    {
        if ((int) $discount->business_id !== (int) $authUser->business_id) {
            abort(403, 'Unauthorized access to this discount');
        }
    }
}
