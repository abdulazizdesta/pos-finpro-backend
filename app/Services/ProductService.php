<?php

namespace App\Services;

use App\Http\Requests\BulkImportProductRequest;
use App\Http\Requests\StoreProductRequest;
use App\Http\Requests\UpdateProductRequest;
use App\Models\Product;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ProductService
{
    public function getAll(User $authUser): LengthAwarePaginator
    {
        $perPage = min((int) request('per_page', 15), 100);
        $search = request('search');
        $category = request('category_id');
        $active = request('is_active');

        $query = Product::query()
            ->with(['category:id,name'])
            ->when($search, fn($q) => $q->whereFullText(['name', 'sku'], $search))
            ->when($category, fn($q) => $q->where('category_id', $category))
            ->when(!is_null($active), fn($q) => $q->where('is_active', filter_var($active, FILTER_VALIDATE_BOOLEAN)));

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

        $imageUrl = $this->uploadImage($request);

        return Product::create([
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
    }

    public function update(UpdateProductRequest $request, Product $product): Product
    {
        $imageUrl = $this->uploadImage($request, $product->image_url);

        $product->update([
            'category_id' => $request->input('category_id', $product->category_id),
            'name' => $request->input('name', $product->name),
            'sku' => $request->input('sku', $product->sku),
            'description' => $request->input('description', $product->description),
            'price' => $request->input('price', $product->price),
            'cost_price' => $request->input('cost_price', $product->cost_price),
            'image_url' => $imageUrl,
            'has_variants' => $request->has('has_variants') ? $request->boolean('has_variants') : $product->has_variants,
            'is_active' => $request->has('is_active') ? $request->boolean('is_active') : $product->is_active,
        ]);

        return $product->fresh(['category:id,name']);
    }

    public function delete(Product $product): void
    {
        $product->delete();
    }

    public function forceDelete(Product $product): void
    {
        if ($product->image_url) {
            Storage::delete(str_replace('/storage/', 'public/', $product->image_url));
        }
        $product->forceDelete();
    }

    public function bulkDelete(array $ids, User $authUser)
    {
        $query = Product::whereIn('id', $ids);

        if ($authUser->role->value !== 'superadmin') {
            $query->where('business_id', $authUser->business_id);
        }

        return $query->delete();
    }

    public function bulkImport(BulkImportProductRequest $request, User $authUser): array
    {
        $businessId = $authUser->role->value === 'superadmin'
            ? $request->business_id
            : $authUser->business_id;
        $file = $request->file('file')->getRealPath();
        $handle = fopen($file, 'r');

        $header = fgetcsv($handle);
        $header = array_map('trim', $header);

        $success = 0;
        $failed = [];
        $row = 1;

        while (($line = fgetcsv($handle)) !== false) {
            $row++;

            if (count($line) !== count($header)) {
                $failed[] = [
                    'row' => $row,
                    'reason' => 'Column count mismatch'
                ];
                continue;
            }

            $data = array_combine($header, $line);

            if (empty($data['name']) || empty($data['price'])) {
                $failed[] = [
                    'row' => $row,
                    'reason' => 'Name and price are required'
                ];
                continue;
            }

            $sku = !empty($data['sku']) ? trim($data['sku']) : $this->generateSku($data['name']);
            if (Product::where('sku', $sku)->exists()) {
                $failed[] = ['row' => $row, 'reason' => "SKU '{$sku}' already exists"];
                continue;
            }

            try {
                Product::create([
                    'business_id' => $businessId,
                    'category_id' => !empty($data['category_id']) ? (int) $data['category_id'] : null,
                    'name' => trim($data['name']),
                    'sku' => $sku,
                    'description' => $data['description'] ?? null,
                    'price' => (float) $data['price'],
                    'cost_price' => !empty($data['cost_price']) ? (float) $data['cost_price'] : null,
                    'has_variants' => filter_var($data['has_variants'] ?? false, FILTER_VALIDATE_BOOLEAN),
                    'is_active' => filter_var($data['is_active'] ?? true, FILTER_VALIDATE_BOOLEAN),
                ]);
                $success++;
            } catch (\Throwable $e) {
                $failed[] = ['row' => $row, 'reason' => $e->getMessage()];
            }
        }
        fclose($handle);

        return [
            'imported' => $success,
            'failed' => count($failed),
            'errors' => $failed,
        ];
    }

    public function authorizeAccess(User $authUser, Product $product): void
    {
        if ($authUser->role->value === 'superadmin')
            return;

        if ($authUser->business_id !== $product->business_id) {
            abort(403, 'Unauthorized');
        }
    }

    private function uploadImage($request, ?string $oldImageUrl = null): ?string
    {
        if (!$request->hasFile('image'))
            return $oldImageUrl;

        if ($oldImageUrl) {
            Storage::delete(str_replace('/storage/', 'public/', $oldImageUrl));
        }

        return Storage::url($request->file('image')->store('public/products'));
    }

    private function generateSku(string $name): string
    {
        $prefix = strtoupper(substr(preg_replace('/[^a-zA-Z0-9]/', '', $name), 0, 4));
        return $prefix . '-' . strtoupper(Str::random(6));
    }
}