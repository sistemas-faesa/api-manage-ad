<?php

use App\Http\Controllers\System\ManageUserController;
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

Route::middleware(['access.security'])->prefix('v1')->group(function(){
    Route::post('ad-manage/auth', [LoginRequest::class, 'authenticate']);
    Route::get('ad-manage/list-users', [ManageUserController::class, 'listAllUsers']);
    Route::post('ad-manage/create-user', [ManageUserController::class, 'createUser']);
});

Route::prefix('v1')->middleware('jwt.auth')->group(function(){
    Route::get('ad-manage/me', [LoginRequest::class, "me"]);
    Route::get('ad-manage/refresh', [LoginRequest::class, "refresh"]);
    Route::get('ad-manage/logout', [LoginRequest::class, "logout"]);
});
