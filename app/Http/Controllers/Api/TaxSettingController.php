<?php

namespace App\Http\Controllers\Api;

use App\Helpers\ApiLogger;
use App\Helpers\ApiMessage;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreTaxSettingRequest;
use App\Http\Requests\UpdateTaxSettingRequest;
use App\Models\TaxSetting;
use App\Services\TaxSettingService;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Throwable;

class TaxSettingController extends Controller
{
    public function __construct(private TaxSettingService $service) {}

    public function index()
    {
        try {
            $data = $this->service->getAll(auth()->user());
            return ApiMessage::paginated('Success get all tax settings', $data);
        } catch (Throwable $th) {
            ApiLogger::error('TaxSettingController@index', $th);
            return ApiMessage::error('Something went wrong', [], 500);
        }
    }

    public function store(StoreTaxSettingRequest $request)
    {
        try {
            $data = $this->service->create($request, auth()->user());
            return ApiMessage::success('Tax setting created successfully', $data, 201);
        } catch (HttpException $e) {
            throw $e;
        } catch (Throwable $th) {
            ApiLogger::error('TaxSettingController@store', $th);
            return ApiMessage::error('Something went wrong', [], 500);
        }
    }

    public function show(TaxSetting $taxSetting)
    {
        try {
            $data = $this->service->getDetail($taxSetting, auth()->user());
            return ApiMessage::success('Success get tax setting', $data);
        } catch (HttpException $e) {
            throw $e;
        } catch (Throwable $th) {
            ApiLogger::error('TaxSettingController@show', $th);
            return ApiMessage::error('Something went wrong', [], 500);
        }
    }

    public function update(UpdateTaxSettingRequest $request, TaxSetting $taxSetting)
    {
        try {
            $data = $this->service->update($request, $taxSetting, auth()->user());
            return ApiMessage::success('Tax setting updated successfully', $data);
        } catch (HttpException $e) {
            throw $e;
        } catch (Throwable $th) {
            ApiLogger::error('TaxSettingController@update', $th);
            return ApiMessage::error('Something went wrong', [], 500);
        }
    }

    public function destroy(TaxSetting $taxSetting)
    {
        try {
            $this->service->delete($taxSetting, auth()->user());
            return ApiMessage::success('Tax setting deleted successfully', null);
        } catch (HttpException $e) {
            throw $e;
        } catch (Throwable $th) {
            ApiLogger::error('TaxSettingController@destroy', $th);
            return ApiMessage::error('Something went wrong', [], 500);
        }
    }
}
