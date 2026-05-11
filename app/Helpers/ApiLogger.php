<?php

namespace App\Helpers;

use Illuminate\Support\Facades\Log;
use Throwable;

class ApiLogger
{
    public static function error(string $location, Throwable $th): void
    {
        Log::channel('api')->error($location, [
            'message'  => $th->getMessage(),
            'file'     => $th->getFile(),
            'line'     => $th->getLine(),
            'user_id'  => auth()->id(),
            'url'      => request()->fullUrl(),
            'method'   => request()->method(),
            'input'    => request()->except(['password', 'pin']),
        ]);
    }
}