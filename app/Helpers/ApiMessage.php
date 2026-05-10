<?php

namespace App\Helpers;

use Illuminate\Http\JsonResponse;

class ApiMessage
{

    public static function success($message, $data = null, $code = 200): JsonResponse
    {

        return response()->json([
            "success" => true,
            'message' => $message,
            'data' => $data
        ], $code);
    }

    public static function error($message, $errors, $code = 500): JsonResponse
    {

        return response()->json([
            "success" => false,
            'message' => $message,
            'errors' => $errors
        ], $code);
    }

    public static function paginated($message, $data):JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => $data->items(),
            'meta' => [
                'current_page' => $data->currentPage(),
                'last_page' => $data->lastPage(),
                'per_page' => $data->perPage(),
                'total' => $data->total(),
            ],
        ], 200);
    }

}