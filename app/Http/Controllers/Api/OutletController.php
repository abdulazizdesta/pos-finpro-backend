<?php

namespace App\Http\Controllers\Api;

use App\Helpers\ApiLogger;
use App\Helpers\ApiMessage;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreOutletRequest;
use App\Http\Requests\UpdateOutletRequest;
use App\Models\Outlet;
use App\Services\OutletService;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Throwable;

class OutletController extends Controller
{
    public function __construct(private OutletService $service) {}

    public function index()
    {
        try {
            $data = $this->service->getAll(auth()->user());
            return ApiMessage::paginated('Success get all outlets', $data);
        } catch (Throwable $th) {
            ApiLogger::error('OutletController@index', $th);
            return ApiMessage::error('Something went wrong', [], 500);
        }
    }

    public function store(StoreOutletRequest $request)
    {
        try {
            $data = $this->service->create($request, auth()->user());
            return ApiMessage::success('Outlet created successfully', $data, 201);
        } catch (HttpException $e) {
            throw $e;
        } catch (Throwable $th) {
            ApiLogger::error('OutletController@store', $th);
            return ApiMessage::error('Something went wrong', [], 500);
        }
    }

    public function show(Outlet $outlet)
    {
        try {
            $data = $this->service->getDetail($outlet, auth()->user());
            return ApiMessage::success('Success get outlet', $data);
        } catch (HttpException $e) {
            throw $e;
        } catch (Throwable $th) {
            ApiLogger::error('OutletController@show', $th);
            return ApiMessage::error('Something went wrong', [], 500);
        }
    }

    public function update(UpdateOutletRequest $request, Outlet $outlet)
    {
        try {
            $data = $this->service->update($request, $outlet, auth()->user());
            return ApiMessage::success('Outlet updated successfully', $data);
        } catch (HttpException $e) {
            throw $e;
        } catch (Throwable $th) {
            ApiLogger::error('OutletController@update', $th);
            return ApiMessage::error('Something went wrong', [], 500);
        }
    }

    public function destroy(Outlet $outlet)
    {
        try {
            $this->service->delete($outlet, auth()->user());
            return ApiMessage::success('Outlet deleted successfully', null);
        } catch (HttpException $e) {
            throw $e;
        } catch (Throwable $th) {
            ApiLogger::error('OutletController@destroy', $th);
            return ApiMessage::error('Something went wrong', [], 500);
        }
    }
}
