<?php

namespace App\Http\Controllers\Api;

use App\Helpers\ApiMessage;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginPinRequest;
use App\Http\Requests\Auth\LoginRequest;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Throwable;

class AuthController extends Controller
{
    public function registration(Request $request)
    {
        
    }

    public function login(LoginRequest $request)
    {
        try {
            $user = User::where('email', $request->email)->first();

            if (!$user || !Hash::check($request->password, $user->password)) {
                return ApiMessage::error('Email atau Password salah', [], 401);
            }

            $token = $user->createToken('auth')->plainTextToken;

            return ApiMessage::success('Login Berhasil', [
                'name' => $user->name,
                'role' => $user->role,
                'token' => $token,
            ]);

        } catch (Throwable $th) {
            Log::error($th->getMessage());
            return ApiMessage::error('Something went worng', [], 500);
        }

    }

    public function loginWithPin(LoginPinRequest $request)
    {
        try {
            $user = User::where('email', $request->email)->first();

            if(!$user){
                return ApiMessage::error('Email atau PIN salah', [], 401);
            }
            if(!$user->pin){
                return ApiMessage::error('PIN belum di-set', [], 401);
            }
            if(!Hash::check($request->pin, $user->pin)){
                return ApiMessage::error('Email atau PIN salah', [], 401);
            }

            $token = $user->createToken('auth')->plainTextToken;

            return ApiMessage::success('Login Berhasil', [
                'name' => $user->name,
                'role' => $user->role,
                'token' => $token,
            ]);

        } catch (Throwable $th) {
            Log::error($th->getMessage());
            return ApiMessage::error('Something went wrong', [], 500);
        }
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();
        return ApiMessage::success('Logout successful', null, 200);
    }

    public function me(Request $request)
    {
        $user = auth()->user()->load('business', 'outlet');
        $data = [
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->role,
            'business' => $user->business?->name,
            'outlet'   => $user->outlet?->name,
        ];

        return ApiMessage::success('Succes get own data', $data, 200);
    }
}
