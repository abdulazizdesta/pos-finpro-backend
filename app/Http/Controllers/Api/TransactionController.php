<?php

namespace App\Http\Controllers\Api;

use App\Helpers\ApiLogger;
use App\Helpers\ApiMessage;
use App\Http\Controllers\Controller;
use App\Http\Requests\ConfirmPaymentRequest;
use App\Http\Requests\StoreTransactionRequest;
use App\Models\Transaction;
use App\Services\TransactionService;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Throwable;

class TransactionController extends Controller
{
    public function __construct(private TransactionService $service) {}

    public function index()
    {
        try {
            $data = $this->service->getAll(auth()->user());
            return ApiMessage::paginated('Success get all transactions', $data);
        } catch (Throwable $th) {
            ApiLogger::error('TransactionController@index', $th);
            return ApiMessage::error('Something went wrong', [], 500);
        }
    }

    public function store(StoreTransactionRequest $request)
    {
        try {
            $data = $this->service->create($request, auth()->user());
            return ApiMessage::success('Transaksi berhasil dibuat', $data, 201);
        } catch (HttpException $e) {
            throw $e;
        } catch (Throwable $th) {
            ApiLogger::error('TransactionController@store', $th);
            return ApiMessage::error('Something went wrong', [], 500);
        }
    }

    public function show(Transaction $transaction)
    {
        try {
            $data = $this->service->getDetail($transaction, auth()->user());
            return ApiMessage::success('Success get transaction', $data);
        } catch (HttpException $e) {
            throw $e;
        } catch (Throwable $th) {
            ApiLogger::error('TransactionController@show', $th);
            return ApiMessage::error('Something went wrong', [], 500);
        }
    }

    public function confirmPayment(ConfirmPaymentRequest $request, Transaction $transaction)
    {
        try {
            $data = $this->service->confirmPayment($request, $transaction, auth()->user());
            return ApiMessage::success('Pembayaran berhasil dikonfirmasi', $data);
        } catch (HttpException $e) {
            throw $e;
        } catch (Throwable $th) {
            ApiLogger::error('TransactionController@confirmPayment', $th);
            return ApiMessage::error('Something went wrong', [], 500);
        }
    }
}
