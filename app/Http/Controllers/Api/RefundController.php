<?php

namespace App\Http\Controllers\Api;

use App\Helpers\ApiLogger;
use App\Helpers\ApiMessage;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreRefundRequest;
use App\Models\Transaction;
use App\Services\RefundService;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Throwable;

class RefundController extends Controller
{
    public function __construct(private RefundService $service) {}

    public function store(StoreRefundRequest $request, Transaction $transaction)
    {
        try {
            $data = $this->service->create($request, $transaction, auth()->user());
            return ApiMessage::success('Refund successfully processed', $data, 201);
        } catch (HttpException $e) {
            throw $e;
        } catch (Throwable $th) {
            ApiLogger::error('RefundController@store', $th);
            return ApiMessage::error('Something went wrong', [], 500);
        }
    }
}