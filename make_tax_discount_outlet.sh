#!/bin/bash
set -e
echo "🚀 Making Tax Settings, Discount, and Outlet CRUD..."

# ============================================================
# TAX SETTINGS
# ============================================================

cat > app/Http/Requests/StoreTaxSettingRequest.php << 'EOF'
<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreTaxSettingRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'name'      => ['required', 'string', 'max:50'],
            'rate'      => ['required', 'numeric', 'min:0', 'max:100'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required'  => 'Tax name is required',
            'name.max'       => 'Tax name must not exceed 50 characters',
            'rate.required'  => 'Tax rate is required',
            'rate.numeric'   => 'Tax rate must be a number',
            'rate.min'       => 'Tax rate must be at least 0',
            'rate.max'       => 'Tax rate must not exceed 100',
        ];
    }
}
EOF
echo "✅ StoreTaxSettingRequest"

cat > app/Http/Requests/UpdateTaxSettingRequest.php << 'EOF'
<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateTaxSettingRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'name'      => ['sometimes', 'string', 'max:50'],
            'rate'      => ['sometimes', 'numeric', 'min:0', 'max:100'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.max'   => 'Tax name must not exceed 50 characters',
            'rate.numeric' => 'Tax rate must be a number',
            'rate.min'   => 'Tax rate must be at least 0',
            'rate.max'   => 'Tax rate must not exceed 100',
        ];
    }
}
EOF
echo "✅ UpdateTaxSettingRequest"

cat > app/Services/TaxSettingService.php << 'EOF'
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
EOF
echo "✅ TaxSettingService"

cat > app/Http/Controllers/Api/TaxSettingController.php << 'EOF'
<?php

namespace App\Http\Controllers\Api;

use App\Helpers\ApiLogger;
use App\Helpers\ApiMessage;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreTaxSettingRequest;
use App\Http\Requests\UpdateTaxSettingRequest;
use App\Models\TaxSetting;
use App\Services\TaxSettingService;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Throwable;

class TaxSettingController extends Controller
{
    public function __construct(private TaxSettingService $service) {}

    public function index()
    {
        try {
            $data = $this->service->getAll(auth()->user());
            return ApiMessage::paginated('Success get all tax settings', $data);
        } catch (Throwable $th) {
            ApiLogger::error('TaxSettingController@index', $th);
            return ApiMessage::error('Something went wrong', [], 500);
        }
    }

    public function store(StoreTaxSettingRequest $request)
    {
        try {
            $data = $this->service->create($request, auth()->user());
            return ApiMessage::success('Tax setting created successfully', $data, 201);
        } catch (HttpException $e) {
            throw $e;
        } catch (Throwable $th) {
            ApiLogger::error('TaxSettingController@store', $th);
            return ApiMessage::error('Something went wrong', [], 500);
        }
    }

    public function show(TaxSetting $taxSetting)
    {
        try {
            $data = $this->service->getDetail($taxSetting, auth()->user());
            return ApiMessage::success('Success get tax setting', $data);
        } catch (HttpException $e) {
            throw $e;
        } catch (Throwable $th) {
            ApiLogger::error('TaxSettingController@show', $th);
            return ApiMessage::error('Something went wrong', [], 500);
        }
    }

    public function update(UpdateTaxSettingRequest $request, TaxSetting $taxSetting)
    {
        try {
            $data = $this->service->update($request, $taxSetting, auth()->user());
            return ApiMessage::success('Tax setting updated successfully', $data);
        } catch (HttpException $e) {
            throw $e;
        } catch (Throwable $th) {
            ApiLogger::error('TaxSettingController@update', $th);
            return ApiMessage::error('Something went wrong', [], 500);
        }
    }

    public function destroy(TaxSetting $taxSetting)
    {
        try {
            $this->service->delete($taxSetting, auth()->user());
            return ApiMessage::success('Tax setting deleted successfully', null);
        } catch (HttpException $e) {
            throw $e;
        } catch (Throwable $th) {
            ApiLogger::error('TaxSettingController@destroy', $th);
            return ApiMessage::error('Something went wrong', [], 500);
        }
    }
}
EOF
echo "✅ TaxSettingController"

# ============================================================
# DISCOUNT
# ============================================================

cat > app/Http/Requests/StoreDiscountRequest.php << 'EOF'
<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreDiscountRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'code'         => ['required', 'string', 'max:50', 'unique:discounts,code'],
            'name'         => ['nullable', 'string', 'max:100'],
            'type'         => ['required', 'in:percentage,fixed'],
            'value'        => ['required', 'integer', 'min:1'],
            'min_purchase' => ['nullable', 'integer', 'min:0'],
            'max_uses'     => ['nullable', 'integer', 'min:1'],
            'valid_from'   => ['nullable', 'date'],
            'valid_until'  => ['nullable', 'date', 'after_or_equal:valid_from'],
            'is_active'    => ['nullable', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'code.required'              => 'Discount code is required',
            'code.unique'                => 'Discount code already exists',
            'code.max'                   => 'Discount code must not exceed 50 characters',
            'type.required'              => 'Discount type is required',
            'type.in'                    => 'Discount type must be percentage or fixed',
            'value.required'             => 'Discount value is required',
            'value.integer'              => 'Discount value must be an integer',
            'value.min'                  => 'Discount value must be at least 1',
            'valid_until.after_or_equal' => 'Valid until must be after or equal to valid from',
        ];
    }
}
EOF
echo "✅ StoreDiscountRequest"

cat > app/Http/Requests/UpdateDiscountRequest.php << 'EOF'
<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateDiscountRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'code'         => ['sometimes', 'string', 'max:50', Rule::unique('discounts', 'code')->ignore($this->route('discount'))],
            'name'         => ['sometimes', 'nullable', 'string', 'max:100'],
            'type'         => ['sometimes', 'in:percentage,fixed'],
            'value'        => ['sometimes', 'integer', 'min:1'],
            'min_purchase' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'max_uses'     => ['sometimes', 'nullable', 'integer', 'min:1'],
            'valid_from'   => ['sometimes', 'nullable', 'date'],
            'valid_until'  => ['sometimes', 'nullable', 'date', 'after_or_equal:valid_from'],
            'is_active'    => ['sometimes', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'code.unique'                => 'Discount code already exists',
            'type.in'                    => 'Discount type must be percentage or fixed',
            'value.integer'              => 'Discount value must be an integer',
            'value.min'                  => 'Discount value must be at least 1',
            'valid_until.after_or_equal' => 'Valid until must be after or equal to valid from',
        ];
    }
}
EOF
echo "✅ UpdateDiscountRequest"

cat > app/Services/DiscountService.php << 'EOF'
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
EOF
echo "✅ DiscountService"

cat > app/Http/Controllers/Api/DiscountController.php << 'EOF'
<?php

namespace App\Http\Controllers\Api;

use App\Helpers\ApiLogger;
use App\Helpers\ApiMessage;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreDiscountRequest;
use App\Http\Requests\UpdateDiscountRequest;
use App\Models\Discount;
use App\Services\DiscountService;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Throwable;

class DiscountController extends Controller
{
    public function __construct(private DiscountService $service) {}

    public function index()
    {
        try {
            $data = $this->service->getAll(auth()->user());
            return ApiMessage::paginated('Success get all discounts', $data);
        } catch (Throwable $th) {
            ApiLogger::error('DiscountController@index', $th);
            return ApiMessage::error('Something went wrong', [], 500);
        }
    }

    public function store(StoreDiscountRequest $request)
    {
        try {
            $data = $this->service->create($request, auth()->user());
            return ApiMessage::success('Discount created successfully', $data, 201);
        } catch (HttpException $e) {
            throw $e;
        } catch (Throwable $th) {
            ApiLogger::error('DiscountController@store', $th);
            return ApiMessage::error('Something went wrong', [], 500);
        }
    }

    public function show(Discount $discount)
    {
        try {
            $data = $this->service->getDetail($discount, auth()->user());
            return ApiMessage::success('Success get discount', $data);
        } catch (HttpException $e) {
            throw $e;
        } catch (Throwable $th) {
            ApiLogger::error('DiscountController@show', $th);
            return ApiMessage::error('Something went wrong', [], 500);
        }
    }

    public function update(UpdateDiscountRequest $request, Discount $discount)
    {
        try {
            $data = $this->service->update($request, $discount, auth()->user());
            return ApiMessage::success('Discount updated successfully', $data);
        } catch (HttpException $e) {
            throw $e;
        } catch (Throwable $th) {
            ApiLogger::error('DiscountController@update', $th);
            return ApiMessage::error('Something went wrong', [], 500);
        }
    }

    public function destroy(Discount $discount)
    {
        try {
            $this->service->delete($discount, auth()->user());
            return ApiMessage::success('Discount deleted successfully', null);
        } catch (HttpException $e) {
            throw $e;
        } catch (Throwable $th) {
            ApiLogger::error('DiscountController@destroy', $th);
            return ApiMessage::error('Something went wrong', [], 500);
        }
    }
}
EOF
echo "✅ DiscountController"

# ============================================================
# OUTLET
# ============================================================

cat > app/Http/Requests/StoreOutletRequest.php << 'EOF'
<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreOutletRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'name'      => ['required', 'string', 'max:100'],
            'code'      => ['required', 'string', 'max:10', 'unique:outlets,code', 'alpha_num'],
            'phone'     => ['nullable', 'string', 'max:20'],
            'address'   => ['nullable', 'string'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required'     => 'Outlet name is required',
            'name.max'          => 'Outlet name must not exceed 100 characters',
            'code.required'     => 'Outlet code is required',
            'code.unique'       => 'Outlet code already exists',
            'code.alpha_num'    => 'Outlet code must be alphanumeric',
            'code.max'          => 'Outlet code must not exceed 10 characters',
        ];
    }
}
EOF
echo "✅ StoreOutletRequest"

cat > app/Http/Requests/UpdateOutletRequest.php << 'EOF'
<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateOutletRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'name'      => ['sometimes', 'string', 'max:100'],
            'code'      => ['sometimes', 'string', 'max:10', 'alpha_num', Rule::unique('outlets', 'code')->ignore($this->route('outlet'))],
            'phone'     => ['sometimes', 'nullable', 'string', 'max:20'],
            'address'   => ['sometimes', 'nullable', 'string'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.max'        => 'Outlet name must not exceed 100 characters',
            'code.unique'     => 'Outlet code already exists',
            'code.alpha_num'  => 'Outlet code must be alphanumeric',
            'code.max'        => 'Outlet code must not exceed 10 characters',
        ];
    }
}
EOF
echo "✅ UpdateOutletRequest"

cat > app/Services/OutletService.php << 'EOF'
<?php

namespace App\Services;

use App\Http\Requests\StoreOutletRequest;
use App\Http\Requests\UpdateOutletRequest;
use App\Models\Outlet;
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
        return Outlet::create([
            'business_id' => $authUser->business_id,
            'name'        => $request->name,
            'code'        => strtoupper($request->code),
            'phone'       => $request->phone,
            'address'     => $request->address,
            'is_active'   => $request->is_active ?? true,
        ]);
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
}
EOF
echo "✅ OutletService"

cat > app/Http/Controllers/Api/OutletController.php << 'EOF'
<?php

namespace App\Http\Controllers\Api;

use App\Helpers\ApiLogger;
use App\Helpers\ApiMessage;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreOutletRequest;
use App\Http\Requests\UpdateOutletRequest;
use App\Models\Outlet;
use App\Services\OutletService;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Throwable;

class OutletController extends Controller
{
    public function __construct(private OutletService $service) {}

    public function index()
    {
        try {
            $data = $this->service->getAll(auth()->user());
            return ApiMessage::paginated('Success get all outlets', $data);
        } catch (Throwable $th) {
            ApiLogger::error('OutletController@index', $th);
            return ApiMessage::error('Something went wrong', [], 500);
        }
    }

    public function store(StoreOutletRequest $request)
    {
        try {
            $data = $this->service->create($request, auth()->user());
            return ApiMessage::success('Outlet created successfully', $data, 201);
        } catch (HttpException $e) {
            throw $e;
        } catch (Throwable $th) {
            ApiLogger::error('OutletController@store', $th);
            return ApiMessage::error('Something went wrong', [], 500);
        }
    }

    public function show(Outlet $outlet)
    {
        try {
            $data = $this->service->getDetail($outlet, auth()->user());
            return ApiMessage::success('Success get outlet', $data);
        } catch (HttpException $e) {
            throw $e;
        } catch (Throwable $th) {
            ApiLogger::error('OutletController@show', $th);
            return ApiMessage::error('Something went wrong', [], 500);
        }
    }

    public function update(UpdateOutletRequest $request, Outlet $outlet)
    {
        try {
            $data = $this->service->update($request, $outlet, auth()->user());
            return ApiMessage::success('Outlet updated successfully', $data);
        } catch (HttpException $e) {
            throw $e;
        } catch (Throwable $th) {
            ApiLogger::error('OutletController@update', $th);
            return ApiMessage::error('Something went wrong', [], 500);
        }
    }

    public function destroy(Outlet $outlet)
    {
        try {
            $this->service->delete($outlet, auth()->user());
            return ApiMessage::success('Outlet deleted successfully', null);
        } catch (HttpException $e) {
            throw $e;
        } catch (Throwable $th) {
            ApiLogger::error('OutletController@destroy', $th);
            return ApiMessage::error('Something went wrong', [], 500);
        }
    }
}
EOF
echo "✅ OutletController"

# ============================================================
# ROUTES
# ============================================================
cat > routes/api.php << 'EOF'
<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\DiscountController;
use App\Http\Controllers\Api\OutletController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\RefundController;
use App\Http\Controllers\Api\ShiftController;
use App\Http\Controllers\Api\StockController;
use App\Http\Controllers\Api\TaxSettingController;
use App\Http\Controllers\Api\TransactionController;
use App\Http\Controllers\Api\UserController;
use Illuminate\Support\Facades\Route;

// ─── Auth
Route::prefix('auth')->group(function () {
    Route::post('register', [AuthController::class, 'register']);
    Route::post('login', [AuthController::class, 'login'])->middleware('throttle:auth');
    Route::post('login/pin', [AuthController::class, 'loginWithPin'])->middleware('throttle:auth');

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('logout', [AuthController::class, 'logout']);
        Route::get('me', [AuthController::class, 'me']);
    });
});

// ─── Protected Routes
Route::middleware(['auth:sanctum', 'throttle:api'])->group(function () {

    // Superadmin & Owner only
    Route::middleware('role:superadmin,owner')->group(function () {
        Route::apiResource('users', UserController::class);
        Route::apiResource('outlets', OutletController::class);
        Route::apiResource('tax-settings', TaxSettingController::class);
        Route::apiResource('discounts', DiscountController::class);
    });

    // Superadmin, Owner & Admin
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

    // Semua role
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
        Route::put('transactions/{transaction}/cancel', [TransactionController::class, 'cancel']);
        Route::post('transactions/{transaction}/refund', [RefundController::class, 'store']);
    });

});
EOF
echo "✅ routes/api.php"

echo ""
echo "============================================"
echo "✅ Done! Files created:"
echo "   Tax Settings : Request x2, Service, Controller"
echo "   Discount     : Request x2, Service, Controller"
echo "   Outlet       : Request x2, Service, Controller"
echo "   Routes       : api.php updated"
echo "============================================"
echo ""
echo "Perlu tambah relasi shifts() di Outlet model:"
echo "  public function shifts(): HasMany"
echo "  {"
echo "      return \$this->hasMany(Shift::class);"
echo "  }"