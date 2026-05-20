<?php

namespace App\Http\Controllers\api;

use App\Helpers\ApiLogger;
use App\Helpers\ApiMessage;
use App\Http\Controllers\Controller;
use App\Services\ReportService;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Throwable;

class ReportController extends Controller
{
     public function __construct(private ReportService $service) {}

     public function getSales()
     {
        try {
            $data = $this->service->sales(auth()->user());
            return ApiMessage::success('Success get sales report', $data);
        } catch (HttpException $e) {
            throw $e;
        } catch (Throwable $th) {
            ApiLogger::error('CategoryController@getSales', $th);
            return ApiMessage::error('Something went wrong', [], 500);
        }
     }
}
