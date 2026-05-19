<?php

namespace App\Services;

use App\Http\Requests\BulkImportProductRequest;
use App\Http\Requests\StoreProductRequest;
use App\Http\Requests\UpdateProductRequest;
use App\Jobs\BulkImportProductJob;
use App\Models\Outlet;
use App\Models\Product;
use App\Models\Stock;
use App\Models\User;
use App\Traits\GeneratesSku;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class ProductService
{
    use GeneratesSku;
    public function getAll(User $authUser): LengthAwarePaginator
    {
        $perPage = min((int) request('per_page', 15), 100);
        $search = request('search');
        $category = request('category_id');
        $active = request('is_active');
        $outletId = request('outlet_id');

        $query = Product::query()
            ->with([
                'category:id,name',
                'stock' => function ($q) use ($outletId) {
                    $q->select('id', 'product_id', 'outlet_id', 'quantity');
                    if ($outletId) {
                        $q->where('outlet_id', $outletId);
                    }
                }
            ])
            ->when($search, function ($q) use ($search) {
                if (app()->environment('testing') || DB::getDriverName() === 'sqlite') {
                    $q->where('name', 'like', "%{$search}%")
                        ->orWhere('sku', 'like', "%{$search}%");
                } else {
                    $q->whereFullText(['name', 'sku'], $search);
                }
            })
            ->when($category, fn($q) => $q->where('category_id', $category))
            ->when(!is_null($active), fn($q) => $q->where('is_active', filter_var($active, FILTER_VALIDATE_BOOLEAN)))
            ->when($outletId, fn($q) => $q->whereHas('stock', fn($s) => $s->where('outlet_id', $outletId)));

        if ($authUser->role->value !== 'superadmin') {
            $query->where('business_id', $authUser->business_id);
        }

        return $query->latest()->paginate($perPage);
    }

    public function create(StoreProductRequest $request, User $authUser): Product
    {
        $businessId = $authUser->role->value === 'superadmin'
            ? $request->business_id
            : $authUser->business_id;

        $outlets = Outlet::where('business_id', $authUser->business_id)->get();

        if ($outlets->isEmpty()) {
            abort(422, 'Create outlets first before add some products');
        }

        $imageUrl = $this->uploadNewImage($request);

        $product = Product::create([
            'business_id' => $businessId,
            'category_id' => $request->category_id,
            'name' => $request->name,
            'sku' => $request->sku ?? $this->generateSku($request->name),
            'description' => $request->description,
            'price' => $request->price,
            'cost_price' => $request->cost_price,
            'image_url' => $imageUrl,
            'has_variants' => $request->boolean('has_variants', false),
            'is_active' => $request->boolean('is_active', true),
        ]);

        foreach ($outlets as $outlet) {
            Stock::firstOrCreate([
                'product_id' => $product->id,
                'outlet_id' => $outlet->id,
                'variant_id' => 0,
            ], ['quantity' => 0, 'min_threshold' => 10]);
        }

        return $product;
    }


    public function update(UpdateProductRequest $request, Product $product): Product
    {
        $oldImageUrl = $product->image_url;
        $newImageUrl = $this->uploadNewImage($request);

        try {
            DB::transaction(function () use ($request, $product, $newImageUrl, $oldImageUrl) {
                $product->update([
                    'category_id' => $request->input('category_id', $product->category_id),
                    'name' => $request->input('name', $product->name),
                    'sku' => $request->input('sku', $product->sku),
                    'description' => $request->input('description', $product->description),
                    'price' => $request->input('price', $product->price),
                    'cost_price' => $request->input('cost_price', $product->cost_price),
                    'image_url' => $newImageUrl ?? $oldImageUrl,
                    'has_variants' => $request->has('has_variants') ? $request->boolean('has_variants') : $product->has_variants,
                    'is_active' => $request->has('is_active') ? $request->boolean('is_active') : $product->is_active,
                ]);
            });

            if ($newImageUrl && $oldImageUrl) {
                $this->deleteImageFile($oldImageUrl);
            }

            return $product->fresh(['category:id,name']);
        } catch (\Throwable $e) {
            if ($newImageUrl) {
                $this->deleteImageFile($newImageUrl);
            }
            throw $e;
        }
    }

    public function delete(Product $product, User $authUser): void
    {
        $product->deleted_by = $authUser->id;
        $product->save();
        $product->delete();
    }

    public function forceDelete(Product $product, User $authUser): void
    {
        if ($authUser->role->value !== 'superadmin') {
            abort(403, 'Only superadmin can permanently delete products');
        }

        if ($product->image_url) {
            $this->deleteImageFile($product->image_url);
        }

        $product->forceDelete();
    }

    public function bulkDelete(array $ids, User $authUser): int
    {
        return DB::transaction(function () use ($ids, $authUser) {
            $query = Product::whereIn('id', $ids);

            if ($authUser->role->value !== 'superadmin') {
                $query->where('business_id', $authUser->business_id);
            }

            $query->update(['deleted_by' => $authUser->id]);

            return $query->delete();
        });
    }

    public function bulkImport(BulkImportProductRequest $request, User $authUser): array
    {
        $businessId = $authUser->role->value === 'superadmin'
            ? $request->business_id
            : $authUser->business_id;

        $path = $request->file('file')->store('imports');
        $fullPath = Storage::path($path);

        BulkImportProductJob::dispatch($fullPath, $businessId);

        return [
            'message' => 'Import on processing.',
        ];
    }

    public function authorizeAccess(User $authUser, Product $product): void
    {
        if ($authUser->role->value === 'superadmin')
            return;

        if ((int) $authUser->business_id !== (int) $product->business_id) {
            abort(403, 'Unauthorized');
        }
    }

    private function uploadNewImage($request): ?string
    {
        if (!$request->hasFile('image')) {
            return null;
        }

        $path = $request->file('image')->store('products', 'public');
        return '/storage/' . $path;  // ← manual aja
    }

    private function deleteImageFile(string $imageUrl): void
    {
        $path = str_replace('/storage/', '', $imageUrl);
        Storage::disk('public')->delete($path);
    }
}