<?php

namespace App\Services;

use App\Models\Category;
use App\Http\Requests\StoreCategoryRequest;
use App\Http\Requests\UpdateCategoryRequest;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class CategoryService
{
    public function getAll(User $authUser): LengthAwarePaginator
    {
        $perPage = min((int) request('per_page', 15), 100);
        $search = request('search');

        $query = Category::query()
            ->when($search, fn($q) => $q->where('name', 'like', "%{$search}%"));

        if ($authUser->role->value !== 'superadmin') {
            $query->where('business_id', $authUser->business_id);
        }

        return $query->paginate($perPage);
    }

    public function create(StoreCategoryRequest $request, User $authUser): Category
    {
        $businessId = $authUser->role->value === 'superadmin'
            ? $request->business_id
            : $authUser->business_id;

        return Category::create([
            'business_id' => $businessId,
            'name' => $request->name,
        ]);
    }

    public function update(UpdateCategoryRequest $request, Category $category): Category
    {
        $category->update([
            'name' => $request->name,
        ]);

        return $category->fresh();
    }

    public function delete(Category $category): void
    {
        $category->delete();
    }

    public function bulkDelete(array $ids, User $authUser)
    {
        $query = Category::whereIn('id', $ids);

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

    public function authorizeAccess(User $authUser, Category $category): void
    {
        if ($authUser->role->value === 'superadmin') {
            return;
        }

        if ($authUser->business_id !== $category->business_id) {
            abort(403, 'Unauthorized');
        }
    }
}