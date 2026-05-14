<?php

namespace App\Http\Controllers\Api;

use App\Helpers\ApiLogger;
use App\Helpers\ApiMessage;
use App\Http\Controllers\Controller;
use App\Http\Requests\AdjustStockRequest;
use App\Http\Requests\RestockRequest;
use App\Http\Requests\StoreStockRequest;
use App\Models\Stock;
use App\Services\StockService;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Throwable;

class StockController extends Controller
{
    public function __construct(private StockService $service) {}

    public function index()
    {
        try {
            $data = $this->service->getAll(auth()->user());
            return ApiMessage::paginated('Success get all stocks', $data);
        } catch (Throwable $th) {
            ApiLogger::error('StockController@index', $th);
            return ApiMessage::error('Something went wrong', [], 500);
        }
    }

    public function store(StoreStockRequest $request)
    {
        try {
            $data = $this->service->create($request, auth()->user());
            return ApiMessage::success('Success create stock', $data, 201);
        } catch (HttpException $e) {
            throw $e;
        } catch (Throwable $th) {
            ApiLogger::error('StockController@store', $th);
            return ApiMessage::error('Something went wrong', [], 500);
        }
    }

    public function show(Stock $stock)
    {
        try {
            $data = $this->service->getDetail($stock, auth()->user());
            return ApiMessage::success('Success get stock', $data, 200);
        } catch (HttpException $e) {
            throw $e;
        } catch (Throwable $th) {
            ApiLogger::error('StockController@show', $th);
            return ApiMessage::error('Something went wrong', [], 500);
        }
    }

    public function restock(RestockRequest $request, Stock $stock)
    {
        try {
            $data = $this->service->restock($request, $stock, auth()->user());
            return ApiMessage::success('Success restock', $data, 200);
        } catch (HttpException $e) {
            throw $e;
        } catch (Throwable $th) {
            ApiLogger::error('StockController@restock', $th);
            return ApiMessage::error('Something went wrong', [], 500);
        }
    }

    public function adjust(AdjustStockRequest $request, Stock $stock)
    {
        try {
            $data = $this->service->adjust($request, $stock, auth()->user());
            return ApiMessage::success('Success adjust stock', $data, 200);
        } catch (HttpException $e) {
            throw $e;
        } catch (Throwable $th) {
            ApiLogger::error('StockController@adjust', $th);
            return ApiMessage::error('Something went wrong', [], 500);
        }
    }

    public function mutations()
    {
        try {
            $data = $this->service->getMutations(auth()->user());
            return ApiMessage::paginated('Success get stock mutations', $data);
        } catch (Throwable $th) {
            ApiLogger::error('StockController@mutations', $th);
            return ApiMessage::error('Something went wrong', [], 500);
        }
    }
}