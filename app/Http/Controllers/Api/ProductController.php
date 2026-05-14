<?php

namespace App\Http\Controllers\Api;

use App\Helpers\ApiLogger;
use App\Helpers\ApiMessage;
use App\Http\Controllers\Controller;
use App\Http\Requests\BulkDeleteProductRequest;
use App\Http\Requests\BulkImportProductRequest;
use App\Http\Requests\StoreProductRequest;
use App\Http\Requests\UpdateProductRequest;
use App\Models\Product;
use App\Services\ProductService;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Throwable;

class ProductController extends Controller
{
    public function __construct(private ProductService $service)
    {
    }

    public function index()
    {
        try {
            $data = $this->service->getAll(auth()->user());
            return ApiMessage::paginated('Success get all products', $data);
        } catch (HttpException $e) {
            throw $e;
        } catch (Throwable $th) {
            ApiLogger::error('CategoryController@index', $th);
            return ApiMessage::error('Something went wrong', [], 500);
        }
    }

    public function store(StoreProductRequest $request)
    {
        try {
            $data = $this->service->create($request, auth()->user());
            return ApiMessage::success('Success create product', $data, 201);
        } catch (HttpException $e) {
            throw $e;
        } catch (Throwable $th) {
            ApiLogger::error('CategoryController@store', $th);
            return ApiMessage::error('Something went wrong', [], 500);
        }
    }

    public function show(Product $product)
    {
        try {
            $this->service->authorizeAccess(auth()->user(), $product);
            return ApiMessage::success('Success get product', $product->load('category:id,name'), 200);
        } catch (HttpException $e) {
            throw $e;
        } catch (Throwable $th) {
            ApiLogger::error('CategoryController@show', $th);
            return ApiMessage::error('Something went wrong', [], 500);
        }
    }

    public function update(UpdateProductRequest $request, Product $product)
    {
        try {
            $this->service->authorizeAccess(auth()->user(), $product);
            $data = $this->service->update($request, $product);
            return ApiMessage::success('Success update product', $data, 200);
        } catch (HttpException $e) {
            throw $e;
        } catch (Throwable $th) {
            ApiLogger::error('CategoryController@update', $th);
            return ApiMessage::error('Something went wrong', [], 500);
        }
    }

    public function destroy(Product $product)
    {
        try {
            $this->service->authorizeAccess(auth()->user(), $product);
            $this->service->delete($product, auth()->user());
            return ApiMessage::success('Success delete product', null, 200);
        } catch (HttpException $e) {
            throw $e;
        } catch (Throwable $th) {
            ApiLogger::error('CategoryController@destroy', $th);
            return ApiMessage::error('Something went wrong', [], 500);
        }
    }

    public function bulkDelete(BulkDeleteProductRequest $request)
    {
        try {
            $deleted = $this->service->bulkDelete($request->ids, auth()->user());
            return ApiMessage::success("Success delete {$deleted} products", null, 200);
        } catch (HttpException $e) {
            throw $e;
        } catch (Throwable $th) {
            ApiLogger::error('CategoryController@bulkDelete', $th);
            return ApiMessage::error('Something went wrong', [], 500);
        }
    }

    public function bulkImport(BulkImportProductRequest $request)
    {
        try {
            $result = $this->service->bulkImport($request, auth()->user());
            return ApiMessage::success('Bulk import completed', $result, 200);
        } catch (HttpException $e) {
            throw $e;
        } catch (Throwable $th) {
            ApiLogger::error('CategoryController@bulkImport', $th);
            return ApiMessage::error('Something went wrong', [], 500);
        }
    }

    public function forceDelete(Product $product)
    {
        try {
            $this->service->forceDelete($product, auth()->user());
            return ApiMessage::success('Success permanently delete product', null, 200);
        } catch (HttpException $e) {
            throw $e;
        } catch (Throwable $th) {
            ApiLogger::error('CategoryController@forceDelete', $th);
            return ApiMessage::error('Something went wrong', [], 500);
        }
    }
}