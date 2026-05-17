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
