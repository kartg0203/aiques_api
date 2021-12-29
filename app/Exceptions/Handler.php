<?php

namespace App\Exceptions;

use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;

use App\Http\Responses\ResponseJson;

use Illuminate\Database\QueryException;
// 這是用來捕獲數據錯誤的，例如：資料庫連接失敗，查詢欄位不存在等等;

use Illuminate\Auth\Access\AuthorizationException;
// 這是用來捕獲身份驗證錯誤的，例如：token 過期;

use Illuminate\Auth\AuthenticationException;
// 身分驗證未通過，例如：未登入

use Illuminate\Validation\ValidationException;
// 這是用來捕獲表單驗證錯誤的，例如：參數格式不對、參數缺失等等;

use \Symfony\Component\HttpKernel\Exception\HttpException;
// 這是用來捕獲 http 請求異常的，例如：url 不存在

use Illuminate\Database\Eloquent\ModelNotFoundException;
// 這是找不到model時會出現的異常，例如：findOrFail

use \Illuminate\Session\TokenMismatchException;
// 這是 捕獲csrf token問題

use Throwable;

class Handler extends ExceptionHandler
{

    use ResponseJson;
    /**
     * A list of the exception types that are not reported.
     *
     * @var array
     */
    protected $dontReport = [
        //
    ];

    /**
     * A list of the inputs that are never flashed for validation exceptions.
     *
     * @var array
     */
    protected $dontFlash = [
        'current_password',
        'password',
        'password_confirmation',
    ];

    /**
     * Register the exception handling callbacks for the application.
     *
     * @return void
     */
    public function register()
    {
        $this->reportable(function (Throwable $e) {
        });
    }

    /**
     * 錯誤處理
     */
    public function render($request, Throwable $e)
    {
        $error = [];

        if ($e instanceof QueryException) {
            // 資料庫錯誤
            $code = 500;
            $message = '資料庫錯誤';
            if (env('APP_DEBUG')) {
                $error = ['file' => $e->getFile(), 'message' => $e->getMessage(), 'line' => $e->getLine()];
            }
        } else if ($e instanceof AuthenticationException) {
            // 沒有權限訪問此頁面
            $code = 401;
            $message = '身份未經過驗證';
        } else if ($e instanceof AuthorizationException) {
            $code = 403;
            $message = '沒有權限訪問此頁面';
            if (env('APP_DEBUG')) {
                $error = ['file' => $e->getFile(), 'message' => $e->getMessage(), 'line' => $e->getLine()];
            }
        } else if ($e instanceof HttpException) {
            // 找不到網頁
            $code = 404;
            $message = '找不到網頁';
        } else if ($e instanceof ModelNotFoundException) {
            // 找不到model
            $code = 404;
            $message = '資料不存在';
            if (env('APP_DEBUG')) {
                $error = ['file' => $e->getFile(), 'message' => $e->getMessage(), 'line' => $e->getLine()];
            }
        } else if ($e instanceof ValidationException) {
            // 驗證錯誤
            $code = 422;
            $message = '參數格式錯誤';
            $error = $e->errors();
            if (env('APP_DEBUG')) {
                $error = ['file' => $e->getFile(), 'message' => $e->getMessage(), 'line' => $e->getLine()];
            }
        } else if ($e instanceof TokenMismatchException) {
            // csrf token 找不到
            $code = 419;
            $message = 'CSRF token mismatch';
            if (env('APP_DEBUG')) {
                $error = ['file' => $e->getFile(), 'message' => $e->getMessage(), 'line' => $e->getLine()];
            }
        } else {
            // 其他異常
            $code = 500;
            $message = '伺服器異常';
            if (env('APP_DEBUG')) {
                $error = ['file' => $e->getFile(), 'message' => $e->getMessage(), 'line' => $e->getLine()];
            }
        }

        return $this->jsonErrorsData($code, $message, $error);
    }
}
