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
use Illuminate\Support\Facades\Log;
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
        } catch (Throwable $th) {
            ApiLogger::error('ProductController@index', $th);
            return ApiMessage::error('Something went wrong', [], 500);
        }
    }

    public function store(StoreProductRequest $request)
    {
        try {
            $data = $this->service->create($request, auth()->user());
            return ApiMessage::success('Success create product', $data, 201);
        } catch (Throwable $th) {
            ApiLogger::error('ProductController@index', $th);
            return ApiMessage::error('Something went wrong', [], 500);
        }
    }

    public function show(Product $product)
    {
        try {
            $this->service->authorizeAccess(auth()->user(), $product);
            return ApiMessage::success('Success get product', $product->load('category:id,name'), 200);
        } catch (Throwable $th) {
            ApiLogger::error('ProductController@index', $th);
            return ApiMessage::error('Something went wrong', [], 500);
        }
    }

    public function update(UpdateProductRequest $request, Product $product)
    {
        try {
            $this->service->authorizeAccess(auth()->user(), $product);
            $data = $this->service->update($request, $product);
            return ApiMessage::success('Success update product', $data, 200);
        } catch (Throwable $th) {
            ApiLogger::error('ProductController@index', $th);
            return ApiMessage::error('Something went wrong', [], 500);
        }
    }

    public function destroy(Product $product)
    {
        try {
            $this->service->authorizeAccess(auth()->user(), $product);
            $this->service->delete($product);
            return ApiMessage::success('Success delete product', null, 200);
        } catch (Throwable $th) {
            ApiLogger::error('ProductController@index', $th);
            return ApiMessage::error('Something went wrong', [], 500);
        }
    }

    public function bulkDelete(BulkDeleteProductRequest $request)
    {
        try {
            $deleted = $this->service->bulkDelete($request->ids, auth()->user());
            return ApiMessage::success("Success delete {$deleted} products", null, 200);
        } catch (Throwable $th) {
            ApiLogger::error('ProductController@bulkDelete', $th);
            return ApiMessage::error('Something went wrong', [], 500);
        }
    }

    public function bulkImport(BulkImportProductRequest $request)
    {
        try {
            $result = $this->service->bulkImport($request, auth()->user());
            return ApiMessage::success('Bulk import completed', $result, 200);
        } catch (Throwable $th) {
            ApiLogger::error('ProductController@bulkImport', $th);
            return ApiMessage::error('Something went wrong', [], 500);
        }
    }

    public function forceDelete(Product $product)
    {
        try {
            $this->service->forceDelete($product);
            return ApiMessage::success('Success permanently delete product', null, 200);
        } catch (Throwable $th) {
            ApiLogger::error('ProductController@index', $th);
            return ApiMessage::error('Something went wrong', [], 500);
        }
    }
}