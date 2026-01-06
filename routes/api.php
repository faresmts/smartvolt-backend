<?php

use App\Http\Controllers\Api\DeviceController;
use App\Http\Controllers\Api\GroupController;
use App\Http\Controllers\Api\LoginController;
use App\Http\Controllers\Api\RegisterController;
use Illuminate\Support\Facades\Route;

Route::post('/register', RegisterController::class);
Route::post('/login', LoginController::class);

Route::middleware('auth:sanctum')->group(function () {
    Route::apiResource('groups', GroupController::class);
    Route::post('devices/link', [DeviceController::class, 'link']);
    Route::apiResource('devices', DeviceController::class)->except(['store']);
});
