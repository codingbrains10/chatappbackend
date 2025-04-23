<?php

use App\Http\Controllers\BroadcastController;
use App\Http\Controllers\User\MessageController;
use App\Http\Controllers\User\UsersController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\UserAuth\AuthController;
use App\Http\Controllers\UserAuth\MobileAuthController;
use Illuminate\Support\Facades\Broadcast;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/


Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);
Route::post('/forgot-password', [AuthController::class, 'forgotPassword']);
Route::post('/reset-password', [AuthController::class, 'resetPassword']);


// Broadcasting auth route should use 'auth:sanctum' middleware
Route::post('/broadcasting/auth', [BroadcastController::class, 'authenticate'])->middleware('auth:sanctum');

Route::middleware('auth:sanctum')->group(function () {

    Route::get('/user', function (Request $request) {
        return $request->user();
    });
    Route::post('/upload-profile-image/{userId}', [AuthController::class, 'uploadProfileImage']);
    Route::get('/allusers', [UsersController::class, 'getAllUsers']);
    Route::post('/sendmessage', [MessageController::class, 'storeMessage']);
    Route::get('/getmessages', [MessageController::class, 'getMessages']);
    Route::get('/message-delete/{messageId}', [MessageController::class, 'deleteMessage']);
    Route::post('/message/read', [MessageController::class, 'markMessagesAsRead']);
    Route::post('/logout', [AuthController::class, 'logout']);
});


Route::post('/send-otp', [MobileAuthController::class, 'sendOtp']);
Route::post('/verify-otp', [MobileAuthController::class, 'verifyOtp']);
