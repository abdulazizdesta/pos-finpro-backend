<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;

class CloseShiftRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'closing_cash' => ['required', 'numeric', 'min:0'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $shift = $this->route('shift');

            if (!$shift || $shift->status === 'closed')
                return;

            $minimumHours = 4;
            $openedAt = Carbon::parse($shift->opened_at);
            $minimumClose = $openedAt->addHours($minimumHours);

            if (now()->lt($minimumClose)) {
                $remaining = now()->diff($minimumClose);
                $validator->errors()->add(
                    'shift',
                    "Shift belum bisa ditutup. Sisa waktu: {$remaining->h} jam {$remaining->i} menit."
                );
            }
        });
    }
}