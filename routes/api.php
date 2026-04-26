<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
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
| AUTH (protected)
|--------------------------------------------------------------------------
*/
//Utilisateur connecté : voir son profil, update son profil, delete son compte, logout
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/me', [AuthController::class, 'me']);
    Route::put('/me', [UserController::class, 'updateMe']);
    Route::delete('/me', [UserController::class, 'deleteMe']);
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::put('/user/profile', [UserController::class, 'updateProfile']);
    Route::post('/user/image_profile', [UserController::class, 'updateImage']);
    Route::put('/user/password', [UserController::class, 'updatePassword']);
});

// ADMIN + MANAGER uniquement
Route::middleware(['auth:sanctum', 'role:admin,manager'])->group(function () {
    Route::get('/users', [UserController::class, 'index']);
    Route::get('/users/{id}', [UserController::class, 'show']);
    Route::delete('/users/{id}', [UserController::class, 'destroy']);
    

    // emprunt
    Route::get('/loans', [UserController::class, 'get_all_loans']);
});