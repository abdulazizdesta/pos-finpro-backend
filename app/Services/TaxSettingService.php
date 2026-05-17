<?php

namespace App\Services;

use App\Http\Requests\StoreTaxSettingRequest;
use App\Http\Requests\UpdateTaxSettingRequest;
use App\Models\TaxSetting;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class TaxSettingService
{
    public function getAll(User $authUser): LengthAwarePaginator
    {
        $perPage = min((int) request('per_page', 15), 100);

        return TaxSetting::where('business_id', $authUser->business_id)
            ->when(request('is_active') !== null, fn($q) => $q->where('is_active', request('is_active')))
            ->latest('created_at')
            ->paginate($perPage);
    }

    public function getDetail(TaxSetting $taxSetting, User $authUser): TaxSetting
    {
        $this->authorize($taxSetting, $authUser);
        return $taxSetting;
    }

    public function create(StoreTaxSettingRequest $request, User $authUser): TaxSetting
    {
        return TaxSetting::create([
            'business_id' => $authUser->business_id,
            'name'        => $request->name,
            'rate'        => $request->rate,
            'is_active'   => $request->is_active ?? true,
        ]);
    }

    public function update(UpdateTaxSettingRequest $request, TaxSetting $taxSetting, User $authUser): TaxSetting
    {
        $this->authorize($taxSetting, $authUser);

        $taxSetting->update($request->only(['name', 'rate', 'is_active']));

        return $taxSetting->fresh();
    }

    public function delete(TaxSetting $taxSetting, User $authUser): void
    {
        $this->authorize($taxSetting, $authUser);
        $taxSetting->delete();
    }

    private function authorize(TaxSetting $taxSetting, User $authUser): void
    {
        if ((int) $taxSetting->business_id !== (int) $authUser->business_id) {
            abort(403, 'Unauthorized access to this tax setting');
        }
    }
}
