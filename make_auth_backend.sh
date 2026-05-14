#!/bin/bash
# Jalankan dari ROOT project Laravel
set -e

echo "🔧 Setup backend register endpoint..."

cat > app/Http/Requests/Auth/RegisterRequest.php << 'EOF'
<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class RegisterRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'business_name'  => ['required', 'string', 'min:3', 'max:150'],
            'business_code'  => ['required', 'string', 'min:2', 'max:20', 'unique:businesses,code', 'alpha_num'],
            'owner_name'     => ['required', 'string', 'min:3', 'max:100'],
            'email'          => ['required', 'email', 'max:100', 'unique:users,email'],
            'password'       => ['required', 'string', 'min:8', 'confirmed'],
        ];
    }

    public function messages(): array
    {
        return [
            'business_name.required'  => 'Nama bisnis wajib diisi',
            'business_name.min'       => 'Nama bisnis minimal 3 karakter',
            'business_code.required'  => 'Kode bisnis wajib diisi',
            'business_code.unique'    => 'Kode bisnis sudah dipakai',
            'business_code.alpha_num' => 'Kode bisnis hanya boleh huruf dan angka',
            'owner_name.required'     => 'Nama owner wajib diisi',
            'email.required'          => 'Email wajib diisi',
            'email.email'             => 'Format email tidak valid',
            'email.unique'            => 'Email sudah terdaftar',
            'password.required'       => 'Password wajib diisi',
            'password.min'            => 'Password minimal 8 karakter',
            'password.confirmed'      => 'Konfirmasi password tidak cocok',
        ];
    }
}
EOF
echo "✅ app/Http/Requests/Auth/RegisterRequest.php"

cat > app/Services/AuthService.php << 'EOF'
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
    public function register(RegisterRequest $request): array
    {
        return DB::transaction(function () use ($request) {
            $business = Business::create([
                'name'      => $request->business_name,
                'code'      => strtoupper($request->business_code),
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
EOF
echo "✅ app/Services/AuthService.php"

cat > app/Http/Controllers/Api/AuthController.php << 'EOF'
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
        ]);
    }
}
EOF
echo "✅ app/Http/Controllers/Api/AuthController.php"

cat > routes/api.php << 'EOF'
<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\ShiftController;
use App\Http\Controllers\Api\StockController;
use App\Http\Controllers\Api\TransactionController;
use App\Http\Controllers\Api\UserController;
use Illuminate\Support\Facades\Route;

Route::prefix('auth')->group(function () {
    Route::post('register', [AuthController::class, 'register']);
    Route::post('login', [AuthController::class, 'login'])->middleware('throttle:auth');
    Route::post('login/pin', [AuthController::class, 'loginWithPin'])->middleware('throttle:auth');

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('logout', [AuthController::class, 'logout']);
        Route::get('me', [AuthController::class, 'me']);
    });
});

Route::middleware(['auth:sanctum', 'throttle:api'])->group(function () {

    Route::middleware('role:superadmin,owner')->group(function () {
        Route::apiResource('users', UserController::class);
    });

    Route::middleware('role:superadmin,owner,admin')->group(function () {
        Route::apiResource('categories', CategoryController::class);
        Route::delete('products/bulk', [ProductController::class, 'bulkDelete']);
        Route::post('products/bulk-import', [ProductController::class, 'bulkImport']);
        Route::delete('products/{product}/force', [ProductController::class, 'forceDelete'])->withTrashed();
        Route::apiResource('products', ProductController::class);
        Route::get('stocks', [StockController::class, 'index']);
        Route::post('stocks', [StockController::class, 'store']);
        Route::get('stocks/{stock}', [StockController::class, 'show']);
        Route::put('stocks/{stock}/restock', [StockController::class, 'restock']);
        Route::put('stocks/{stock}/adjust', [StockController::class, 'adjust']);
        Route::get('stock-mutations', [StockController::class, 'mutations']);
    });

    Route::middleware('role:superadmin,owner,admin,cashier')->group(function () {
        Route::get('shifts', [ShiftController::class, 'index']);
        Route::post('shifts', [ShiftController::class, 'open']);
        Route::get('shifts/active', [ShiftController::class, 'active']);
        Route::get('shifts/{shift}', [ShiftController::class, 'show']);
        Route::put('shifts/{shift}/close', [ShiftController::class, 'close']);
        Route::get('transactions', [TransactionController::class, 'index']);
        Route::post('transactions', [TransactionController::class, 'store']);
        Route::get('transactions/{transaction}', [TransactionController::class, 'show']);
        Route::put('transactions/{transaction}/confirm-payment', [TransactionController::class, 'confirmPayment']);
    });
});
EOF
echo "✅ routes/api.php"

echo ""
echo "✅ Backend register endpoint selesai!"
echo "   Test di Postman: POST /api/v1/auth/register"