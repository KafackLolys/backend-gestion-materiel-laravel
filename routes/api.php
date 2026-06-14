<?php

use App\Http\Controllers\Api\ProjectorController;
use App\Http\Controllers\Api\AccessoryController;
use App\Http\Controllers\Api\ActivityLogController;
use App\Http\Controllers\Api\LoanController;
use App\Http\Controllers\Api\StatsController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\UserController;

/*
|--------------------------------------------------------------------------
| AUTH (public)
|--------------------------------------------------------------------------
*/
Route::post('/login', [AuthController::class, 'login']);
Route::post('/register', [AuthController::class,'register']);

/*
|--------------------------------------------------------------------------
| PASSWORD RESET (public)
|--------------------------------------------------------------------------
*/
Route::post('/forgot-password', [AuthController::class, 'forgotPassword']);
Route::post('/verify-reset-code', [AuthController::class, 'verifyResetCode']);
Route::post('/reset-password', [AuthController::class, 'resetPassword']);

/*
|--------------------------------------------------------------------------
| AUTH (protected)
|--------------------------------------------------------------------------
*/
//Utilisateur connecté : voir son profil, update son profil, delete son compte, logout ...
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/me', [AuthController::class, 'me']);
    Route::put('/me', [UserController::class, 'updateMe']);
    Route::delete('/me', [UserController::class, 'deleteMe']);
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::put('/user/profile', [UserController::class, 'updateProfile']);
    Route::post('/user/image_profile', [UserController::class, 'updateImage']);
    Route::put('/user/password', [UserController::class, 'updatePassword']);

    // projector
    Route::get('/projectors', [ProjectorController::class, 'index']);
    Route::get('/projectors/{id}', [ProjectorController::class, 'show']);

    // accessories
    Route::get('/accessories', [AccessoryController::class, 'index']);
    Route::get('/accessories/{id}', [AccessoryController::class, 'show']);

    // loans
    Route::get('/loans/me', [LoanController::class, 'getUserLoans']);
    Route::get('/loans/me/active', [LoanController::class, 'checkActiveLoan']);
    Route::delete('/loans/{id}', [LoanController::class, 'destroy']);
    Route::get('/loans', [LoanController::class, 'index']);
    Route::get('/loans/{id}', [LoanController::class, 'show']);
    Route::post('/loans', [LoanController::class, 'store']);
    Route::put('/loans/{id}', [LoanController::class, 'update']);

    // Notifications
     Route::get('/notifications', [NotificationController::class, 'index']);
    Route::post('/notifications/{id}/read', [NotificationController::class, 'markAsRead']);
    Route::post('/notifications/mark-all-read', [NotificationController::class, 'markAllAsRead']);
});

// ADMIN + MANAGER uniquement
Route::middleware(['auth:sanctum', 'role:admin,manager'])->group(function () {
    Route::get('/users', [UserController::class, 'index']);
    Route::get('/users/{id}', [UserController::class, 'show']);
    Route::delete('/users/{id}', [UserController::class, 'destroy']);
    // create
    Route::post('/users', [UserController::class, 'store']);
    // update
    Route::put('/users/{id}', [UserController::class, 'update']);

    // projector
    Route::post('/projectors', [ProjectorController::class, 'store']);
    Route::put('/projectors/{id}', [ProjectorController::class, 'update']);
    Route::delete('/projectors/{id}', [ProjectorController::class, 'destroy']);

    // accessories
    Route::post('/accessories', [AccessoryController::class, 'store']);
    Route::put('/accessories/{id}', [AccessoryController::class, 'update']);
    Route::delete('/accessories/{id}', [AccessoryController::class, 'destroy']);

    // statistics
    Route::get('/stats', [StatsController::class, 'index']);

    // activity logs
    Route::get('/activity-logs', [ActivityLogController::class, 'index']);
});