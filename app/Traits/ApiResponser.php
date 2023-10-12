<?php

namespace App\Traits;

use Illuminate\Http\Response;

trait ApiResponser {

    /**
     * Build a success response
     * ---
     * @param string|array $data
     * @param int $code
     * @return Illuminate\Http\JsonResponse
     */
    public function successResponse($data, $code = Response::HTTP_OK)
    {
        return response()->json(['data' => $data], $code);
    }

    /**
     * Build a error response
     * ---
     * @param string|array $message
     * @param int $code
     * @return Illuminate\Http\JsonResponse
     */
    public function errorResponse($message, $code = Response::HTTP_BAD_REQUEST)
    {
        return response()->json(['error' => $message, 'code' => $code], $code);
    }

    /**
     * Build a error message response
     * ---
     * @param string|array $message
     * @param int $code
     * @return Illuminate\Http\Response
     */
    public function errorMessage($message, $code)
    {
        return response($message, $code)->header('Content-Type', 'application/json');
    }

    /**
     * Build a success message response
     * ---
     * @param string|array $data
     * @param int $code
     * @return Illuminate\Http\Response
     */
    public function successMessage($data, $code = Response::HTTP_OK)
    {
        return response($data, $code)->header('Content-Type', 'application/json');
    }
}
