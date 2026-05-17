<?php

namespace App\Services;

use App\Enums\UserRole;
use App\Http\Requests\Auth\RegisterRequest;
use App\Models\Business;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class AuthService
{
    public function __construct(private BusinessService $businessService) {}
    public function register(RegisterRequest $request): array
    {
        return DB::transaction(function () use ($request) {
            $business = Business::create([
                'name'      => $request->business_name,
                'code'      => $this->businessService->generateBusinessCode($request->business_name),
                'is_active' => true,
            ]);

            $user = User::create([
                'business_id' => $business->id,
                'name'        => $request->owner_name,
                'email'       => $request->email,
                'password'    => Hash::make($request->password),
                'role'        => UserRole::OWNER,
                'is_active'   => true,
            ]);

            $token = $user->createToken('auth')->plainTextToken;

            return [
                'token'    => $token,
                'name'     => $user->name,
                'role'     => $user->role,
                'business' => $business->name,
                'outlet'   => null,
            ];
        });
    }
}
