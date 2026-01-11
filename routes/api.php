<?php

use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\DeviceController;
use App\Http\Controllers\Api\GroupController;
use App\Http\Controllers\Api\IotDataController;
use App\Http\Controllers\Api\LoginController;
use App\Http\Controllers\Api\RegisterController;
use App\Http\Controllers\Api\RoutineController;
use App\Http\Controllers\Api\UsageGoalController;
use Illuminate\Support\Facades\Route;

Route::post('/register', RegisterController::class);
Route::post('/login', LoginController::class);
Route::post('/iot/report', IotDataController::class);

Route::middleware('auth:sanctum')->group(function () {
    Route::get('dashboard/summary', [DashboardController::class, 'summary']);
    Route::get('dashboard/consumption-history', [DashboardController::class, 'consumptionHistory']);
    Route::get('dashboard/voltage-history', [DashboardController::class, 'voltageHistory']);

    Route::apiResource('groups', GroupController::class);
    Route::post('devices/link', [DeviceController::class, 'link']);
    Route::apiResource('devices', DeviceController::class)->except(['store']);
    Route::apiResource('usage-goals', UsageGoalController::class);
    Route::apiResource('routines', RoutineController::class);
});
