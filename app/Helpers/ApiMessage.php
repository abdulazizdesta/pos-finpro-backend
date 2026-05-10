<?php

namespace App\Helpers;

class ApiMessage{

    public static function success($message, $data = null, $code = 200){

        return response()->json([
            "success" => true,
            'message'=> $message,
            'data'=> $data
        ], $code);
    }

    public static function error($message, $errors, $code = 500){

        return response()->json([
            "success" => false,
            'message'=> $message,
            'errors'=> $errors
        ], $code);
    }
    
}