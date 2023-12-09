<?php

use App\Http\Controllers\Auth\ResetPasswordController;
use App\Http\Controllers\Auth\SendTokenResetPasswordController;
use App\Http\Controllers\System\ActiveDirectoryController;
use App\Http\Requests\Auth\LoginRequest;
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

Route::middleware(['access.security', 'cors'])->prefix('v1')->group(function(){
    Route::post('ad-manage/auth', [LoginRequest::class, 'authenticate']);
    Route::post('ad-manage/create-user', [ActiveDirectoryController::class, 'validateSaveUser']);
    Route::get('ad-manage/list-users', [ActiveDirectoryController::class, 'listAllUsers']);
    Route::get('ad-manage/get-groups', [ActiveDirectoryController::class, 'getGroups']);
    Route::get('ad-manage/get-members-group', [ActiveDirectoryController::class, 'getMembersGroup']);
    Route::get('ad-manage/reset-password/get-user-by-cpf', [ResetPasswordController::class, 'getUserByCpf']);
    Route::post('ad-manage/reset-password/send-token', [SendTokenResetPasswordController::class, 'sendToken']);
    Route::post('ad-manage/reset-password/validate-token', [SendTokenResetPasswordController::class, 'validateToken']);
});
// Route::prefix('v1')->middleware('jwt.auth')->group(function(){
//     Route::get('ad-manage/me', [LoginRequest::class, "me"]);
//     Route::get('ad-manage/refresh', [LoginRequest::class, "refresh"]);
//     Route::get('ad-manage/logout', [LoginRequest::class, "logout"]);
// });
