<?php

namespace App\Http\Controllers\Api;

use App\Helpers\ApiMessage;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreCategoryRequest;
use App\Http\Requests\UpdateCategoryRequest;
use App\Models\Category;
use App\Services\CategoryService;
use Illuminate\Support\Facades\Log;
use Throwable;

class CategoryController extends Controller
{
    public function __construct(private CategoryService $service) {}

    public function index()
    {
        try {
            $data = $this->service->getAll(auth()->user());
            return ApiMessage::paginated('Success get all categories', $data);
        } catch (Throwable $th) {
            Log::error('CategoryController@index: ' . $th->getMessage());
            return ApiMessage::error('Something went wrong', 500);
        }
    }

    public function store(StoreCategoryRequest $request)
    {
        try {
            $data = $this->service->create($request, auth()->user());
            return ApiMessage::success('Success create category', $data, 201);
        } catch (Throwable $th) {
            Log::error('CategoryController@store: ' . $th->getMessage());
            return ApiMessage::error('Something went wrong', 500);
        }
    }

    public function show(Category $category)
    {
        try {
            $this->service->authorizeAccess(auth()->user(), $category);
            return ApiMessage::success('Success get category', $category, 200);
        } catch (Throwable $th) {
            Log::error('CategoryController@show: ' . $th->getMessage());
            return ApiMessage::error('Something went wrong', 500);
        }
    }

    public function update(UpdateCategoryRequest $request, Category $category)
    {
        try {
            $this->service->authorizeAccess(auth()->user(), $category);
            $data = $this->service->update($request, $category);
            return ApiMessage::success('Success update category', $data, 200);
        } catch (Throwable $th) {
            Log::error('CategoryController@update: ' . $th->getMessage());
            return ApiMessage::error('Something went wrong', 500);
        }
    }

    public function destroy(Category $category)
    {
        try {
            $this->service->authorizeAccess(auth()->user(), $category);
            $this->service->delete($category);
            return ApiMessage::success('Success delete category', null, 200);
        } catch (Throwable $th) {
            Log::error('CategoryController@destroy: ' . $th->getMessage());
            return ApiMessage::error('Something went wrong', 500);
        }
    }
}