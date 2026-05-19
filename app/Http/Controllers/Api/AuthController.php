<?php

namespace App\Http\Controllers\Api;

use App\Helpers\ApiLogger;
use App\Helpers\ApiMessage;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginPinRequest;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Models\User;
use App\Services\AuthService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Throwable;

class AuthController extends Controller
{
    public function __construct(private AuthService $service) {}

    public function register(RegisterRequest $request)
    {
        try {
            $data = $this->service->register($request);
            return ApiMessage::success('Registrasi berhasil', $data, 201);
        } catch (HttpException $e) {
            throw $e;
        } catch (Throwable $th) {
            ApiLogger::error('AuthController@register', $th);
            return ApiMessage::error('Something went wrong', [], 500);
        }
    }

    public function login(LoginRequest $request)
    {
        try {
            $user = User::where('email', $request->email)->first();

            if (!$user || !Hash::check($request->password, $user->password)) {
                return ApiMessage::error('Email atau Password salah', [], 401);
            }

            if (!$user->is_active) {
                return ApiMessage::error('Akun tidak aktif', [], 403);
            }

            $token = $user->createToken('auth')->plainTextToken;

            return ApiMessage::success('Login Berhasil', [
                'token'    => $token,
                'name'     => $user->name,
                'role'     => $user->role,
                'business' => $user->business?->name,
                'outlet'   => $user->outlet?->name,
                'outlet_id' => $user->outlet?->id,
            ]);
        } catch (Throwable $th) {
            ApiLogger::error('AuthController@login', $th);
            return ApiMessage::error('Something went wrong', [], 500);
        }
    }

    public function loginWithPin(LoginPinRequest $request)
    {
        try {
            $user = User::where('email', $request->email)->first();

            if (!$user)                                      return ApiMessage::error('Email atau PIN salah', [], 401);
            if (!$user->pin)                                 return ApiMessage::error('PIN belum di-set', [], 401);
            if (!Hash::check($request->pin, $user->pin))    return ApiMessage::error('Email atau PIN salah', [], 401);
            if (!$user->is_active)                          return ApiMessage::error('Akun tidak aktif', [], 403);

            $token = $user->createToken('auth')->plainTextToken;

            return ApiMessage::success('Login Berhasil', [
                'token'    => $token,
                'name'     => $user->name,
                'role'     => $user->role,
                'business' => $user->business?->name,
                'outlet'   => $user->outlet?->name,
                'outlet_id'    => $user->outlet?->id,
            ]);
        } catch (Throwable $th) {
            ApiLogger::error('AuthController@loginWithPin', $th);
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
        return ApiMessage::success('Success get own data', [
            'name'     => $user->name,
            'email'    => $user->email,
            'role'     => $user->role,
            'business' => $user->business?->name,
            'outlet'   => $user->outlet?->name,
            'outlet_id'    => $user->outlet?->id,
        ]);
    }
}
