<?php

namespace App\Http\Resources;

use App\Enums\UserRole;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'role' => $this->role->value,
            'is_active' => $this->is_active,
            'business' => $this->whenLoaded('business', fn() => [
                'id' => $this->business->id,
                'name' => $this->business->name,
            ]),
            'outlet' => $this->whenLoaded('outlet', fn() => [
                'id' => $this->outlet->id,
                'name' => $this->outlet->name,
            ]),
            'created_at' => $this->created_at?->toDateTimeString(),
        ];
    }
}
