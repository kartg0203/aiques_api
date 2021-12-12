<?php

namespace App\Http\Responses;

trait ResponseJson
{
    private function jsonResponse($status, $code, $message, $data, $error)
    {
        $result = [
            'status' => $status,
            'message' => $message,
            'data' => $data,
            'error' => $error
        ];
        return response()->json($result, $code, [], JSON_UNESCAPED_UNICODE);
    }


    // 控制器主要調用這個
    public function jsonSuccessData($data, $code = 200, $message = 'request success')
    {
        return $this->jsonResponse('success', $code, $message, $data, []);
    }

    // 這個再 app/Exceptions/Handler抓到錯誤時會調用這個
    public function jsonErrorsData($code, $message = 'request error', $error = [])
    {
        return $this->jsonResponse('error', $code, $message, [], $error);
    }
}
