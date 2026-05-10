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
        $search  = request('search');

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
            'name'        => $request->name,
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