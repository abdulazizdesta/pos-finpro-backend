<?php

namespace App\Http\Controllers\Api;

use App\Helpers\ApiLogger;
use App\Helpers\ApiMessage;
use App\Http\Controllers\Controller;
use App\Services\ReportService;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Throwable;

class ReportController extends Controller
{
    public function __construct(private ReportService $service)
    {
    }

    public function getSales()
    {
        try {
            $data = $this->service->sales(auth()->user());
            return ApiMessage::success('Success get sales report', $data);
        } catch (HttpException $e) {
            throw $e;
        } catch (Throwable $th) {
            ApiLogger::error('ReportController@getSales', $th);
            return ApiMessage::error('Something went wrong', [], 500);
        }
    }

    public function exportSales()
    {
        try {
            return $this->service->exportSales(auth()->user());
        } catch (HttpException $e) {
            throw $e;
        } catch (Throwable $th) {
            ApiLogger::error('ReportController@exportSales', $th);
            return ApiMessage::error('Gagal export laporan', [], 500);
        }
    }
}
