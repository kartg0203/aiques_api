<?php

use App\Http\Controllers\AiLearn\AiLearnController;
use App\Http\Controllers\AiLearn\HistoryRecordController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/


Route::middleware('auth:sanctum')->group(function () {

    // ai學習
    Route::prefix('ailearn')->group(function () {
        // 對話學習
        Route::get('', [AiLearnController::class, 'todayRecord']);
        Route::get('greeting', [AiLearnController::class, 'getGreet']);
        Route::get('randques', [AiLearnController::class, 'randQues']);
        Route::post('{id}', [AiLearnController::class, 'answer']);
        //  歷史紀錄
        Route::get('history', [HistoryRecordController::class, 'historyRecord']);
    });
});

Route::get('notVerify', function () {
    return response()->json(['status' => false, 'error' => ['code' => 401, 'message' => 'unauthenticated']], 401,);
})->name('notVerify');
