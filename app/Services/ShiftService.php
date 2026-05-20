<?php

namespace App\Services;

use App\Http\Requests\CloseShiftRequest;
use App\Http\Requests\OpenShiftRequest;
use App\Models\Outlet;
use App\Models\Shift;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;

class ShiftService
{
    public function open(OpenShiftRequest $request, User $authUser): Shift
    {
        $this->authorizeOutlet($authUser, $request->outlet_id);

        $alreadyOpen = Shift::where('outlet_id', $request->outlet_id)
            ->where('status', 'open')
            ->exists();

        if ($alreadyOpen) {
            abort(422, 'This outlet already has a shift running');
        }

        return Shift::create([
            'user_id' => $authUser->id,
            'outlet_id' => $request->outlet_id,
            'opened_at' => now(),
            'opening_cash' => $request->opening_cash,
            'status' => 'open',
        ]);
    }

    public function getActive(User $authUser, int $outletId): Shift
    {
        $this->authorizeOutlet($authUser, $outletId);

        $shift = Shift::where('outlet_id', $outletId)
            ->where('status', 'open')
            ->with(['user:id,name', 'outlet:id,name'])
            ->first();

        if (!$shift) {
            abort(404, 'There are no shifts currently running at this outlet');
        }

        return $shift;
    }

    public function getAll(User $authUser): LengthAwarePaginator
    {
        $perPage = min((int) request('per_page', 15), 100);

        return Shift::query()
            ->whereHas('outlet', fn($q) => $q->where('business_id', $authUser->business_id))
            ->with(['user:id,name', 'outlet:id,name'])
            ->when(request('outlet_id'), fn($q) => $q->where('outlet_id', request('outlet_id')))
            ->when(request('status'), fn($q) => $q->where('status', request('status')))
            ->when(request('date_from'), fn($q) => $q->whereDate('opened_at', '>=', request('date_from')))
            ->when(request('date_to'), fn($q) => $q->whereDate('opened_at', '<=', request('date_to')))
            ->latest('opened_at')
            ->paginate($perPage);
    }

    public function getDetail(Shift $shift, User $authUser): Shift
    {
        $this->authorizeOutlet($authUser, $shift->outlet_id);

        return $shift->load(['user:id,name', 'outlet:id,name']);
    }

    public function close(CloseShiftRequest $request, Shift $shift, User $authUser): Shift
    {
        $this->authorizeOutlet($authUser, $shift->outlet_id);

        if ($shift->status === 'closed') {
            abort(422, 'Shift sudah ditutup');
        }

        $minimumClose = Carbon::parse($shift->opened_at)->addHours(4);
        if (now()->lt($minimumClose)) {
            abort(422, 'Shift cannot be closed yet.');
        }

        $shift->update([
            'closed_at' => now(),
            'closing_cash' => $request->closing_cash,
            'status' => 'closed',
        ]);

        return $shift->fresh(['user:id,name', 'outlet:id,name']);
    }

    private function authorizeOutlet(User $authUser, int $outletId): void
    {
        $outlet = Outlet::findOrFail($outletId);

        if ((int) $authUser->business_id !== (int) $outlet->business_id) {
            abort(403, 'Unauthorized access to this outlet');
        }
    }
}