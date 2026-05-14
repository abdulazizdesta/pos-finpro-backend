<?php

namespace App\Http\Controllers\Api;

use App\Helpers\ApiLogger;
use App\Helpers\ApiMessage;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreCategoryRequest;
use App\Http\Requests\UpdateCategoryRequest;
use App\Models\Category;
use App\Services\CategoryService;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Throwable;

class CategoryController extends Controller
{
    public function __construct(private CategoryService $service)
    {
    }

    public function index()
    {
        try {
            $data = $this->service->getAll(auth()->user());
            return ApiMessage::paginated('Success get all categories', $data);
        } catch (HttpException $e) {
            throw $e;
        } catch (Throwable $th) {
            ApiLogger::error('CategoryController@index', $th);
            return ApiMessage::error('Something went wrong', [], 500);
        }
    }

    public function store(StoreCategoryRequest $request)
    {
        try {
            $data = $this->service->create($request, auth()->user());
            return ApiMessage::success('Success create category', $data, 201);
        } catch (HttpException $e) {
            throw $e;
        } catch (Throwable $th) {
            ApiLogger::error('CategoryController@store', $th);
            return ApiMessage::error('Something went wrong', [], 500);
        }
    }

    public function show(Category $category)
    {
        try {
            $this->service->authorizeAccess(auth()->user(), $category);
            return ApiMessage::success('Success get category', $category, 200);
        } catch (HttpException $e) {
            throw $e;
        } catch (Throwable $th) {
            ApiLogger::error('CategoryController@show', $th);
            return ApiMessage::error('Something went wrong', [], 500);
        }
    }

    public function update(UpdateCategoryRequest $request, Category $category)
    {
        try {
            $this->service->authorizeAccess(auth()->user(), $category);
            $data = $this->service->update($request, $category);
            return ApiMessage::success('Success update category', $data, 200);
        } catch (HttpException $e) {
            throw $e;
        } catch (Throwable $th) {
            ApiLogger::error('CategoryController@update', $th);
            return ApiMessage::error('Something went wrong', [], 500);
        }
    }

    public function destroy(Category $category)
    {
        try {
            $this->service->authorizeAccess(auth()->user(), $category);
            $this->service->delete($category);
            return ApiMessage::success('Success delete category', null, 200);
        } catch (HttpException $e) {
            throw $e;
        } catch (Throwable $th) {
            ApiLogger::error('CategoryController@destroy', $th);
            return ApiMessage::error('Something went wrong', [], 500);
        }
    }
}