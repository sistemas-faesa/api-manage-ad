<?php

use App\Http\Controllers\Auth\ResetPasswordController;
use App\Http\Controllers\Auth\SendTokenResetPasswordController;
use App\Http\Controllers\System\ActiveDirectoryController;
use App\Http\Controllers\System\GroupController;
use App\Http\Controllers\System\SearchController;
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

    Route::patch('ad-manage/change-user/admin', [ActiveDirectoryController::class, 'changeUser']);
    Route::post('ad-manage/create-user', [ActiveDirectoryController::class, 'validateSaveUser']);

    Route::get('ad-manage/get-user-by-cpf/admin', [SearchController::class, 'getUserByCpf']);
    Route::get('ad-manage/search-user/admin', [SearchController::class, 'searchUser']);
    Route::get('ad-manage/list-users', [SearchController::class, 'listAllUsers']);

    Route::get('ad-manage/get-members-group', [GroupController::class, 'getMembersGroup']);
    Route::get('ad-manage/get-groups', [GroupController::class, 'getGroups']);

    Route::post('ad-manage/reset-password/validate-token', [SendTokenResetPasswordController::class, 'validateToken']);
    Route::get('ad-manage/reset-password/get-user-by-cpf', [SendTokenResetPasswordController::class, 'getUserByCpf']);
    Route::patch('ad-manage/change-password/admin', [SendTokenResetPasswordController::class, 'changePasswordAdmin']);
    Route::patch('ad-manage/change-password/', [SendTokenResetPasswordController::class, 'changePasswordPublic']);
    Route::post('ad-manage/reset-password/send-token', [SendTokenResetPasswordController::class, 'sendToken']);
});
// Route::prefix('v1')->middleware('jwt.auth')->group(function(){
//     Route::get('ad-manage/me', [LoginRequest::class, "me"]);
//     Route::get('ad-manage/refresh', [LoginRequest::class, "refresh"]);
//     Route::get('ad-manage/logout', [LoginRequest::class, "logout"]);
// });
