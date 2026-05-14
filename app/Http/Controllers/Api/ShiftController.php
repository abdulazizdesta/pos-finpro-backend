<?php

namespace App\Http\Controllers\Api;

use App\Helpers\ApiLogger;
use App\Helpers\ApiMessage;
use App\Http\Controllers\Controller;
use App\Http\Requests\CloseShiftRequest;
use App\Http\Requests\OpenShiftRequest;
use App\Models\Shift;
use App\Services\ShiftService;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Throwable;

class ShiftController extends Controller
{
    public function __construct(private ShiftService $service) {}

    public function index()
    {
        try {
            $data = $this->service->getAll(auth()->user());
            return ApiMessage::paginated('Success get all shifts', $data);
        } catch (Throwable $th) {
            ApiLogger::error('ShiftController@index', $th);
            return ApiMessage::error('Something went wrong', [], 500);
        }
    }

    public function open(OpenShiftRequest $request)
    {
        try {
            $data = $this->service->open($request, auth()->user());
            return ApiMessage::success('Shift berhasil dibuka', $data, 201);
        } catch (HttpException $e) {
            throw $e;
        } catch (Throwable $th) {
            ApiLogger::error('ShiftController@open', $th);
            return ApiMessage::error('Something went wrong', [], 500);
        }
    }

    public function close(CloseShiftRequest $request, Shift $shift)
    {
        try {
            $data = $this->service->close($request, $shift, auth()->user());
            return ApiMessage::success('Shift berhasil ditutup', $data);
        } catch (HttpException $e) {
            throw $e;
        } catch (Throwable $th) {
            ApiLogger::error('ShiftController@close', $th);
            return ApiMessage::error('Something went wrong', [], 500);
        }
    }

    public function active()
    {
        try {
            $outletId = (int) request('outlet_id');

            if (!$outletId) {
                return ApiMessage::error('outlet_id wajib diisi', [], 422);
            }

            $data = $this->service->getActive(auth()->user(), $outletId);
            return ApiMessage::success('Success get active shift', $data);
        } catch (HttpException $e) {
            throw $e;
        } catch (Throwable $th) {
            ApiLogger::error('ShiftController@active', $th);
            return ApiMessage::error('Something went wrong', [], 500);
        }
    }

    public function show(Shift $shift)
    {
        try {
            $data = $this->service->getDetail($shift, auth()->user());
            return ApiMessage::success('Success get shift', $data);
        } catch (HttpException $e) {
            throw $e;
        } catch (Throwable $th) {
            ApiLogger::error('ShiftController@show', $th);
            return ApiMessage::error('Something went wrong', [], 500);
        }
    }
}