<?php

use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\DeviceController;
use App\Http\Controllers\Api\GroupController;
use App\Http\Controllers\Api\LoginController;
use App\Http\Controllers\Api\RegisterController;
use App\Http\Controllers\Api\UsageGoalController;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Route;

Route::post('/register', RegisterController::class);
Route::post('/login', LoginController::class);

Route::middleware('auth:sanctum')->group(function () {
    Route::get('dashboard/summary', [DashboardController::class, 'summary']);
    Route::get('dashboard/consumption-history', [DashboardController::class, 'consumptionHistory']);
    Route::get('dashboard/voltage-history', [DashboardController::class, 'voltageHistory']);

    Route::apiResource('groups', GroupController::class);
    Route::post('groups/{group}/unlink-device/{device}', [GroupController::class, 'unlinkDevice'])->name('groups.unlink-device');
    Route::post('devices/link', [DeviceController::class, 'link']);
    Route::apiResource('devices', DeviceController::class)->except(['store']);
    Route::post('devices/{device}/toggle', [DeviceController::class, 'toggle'])->name('devices.toggle');

    Route::get('/usage-goals', [UsageGoalController::class, 'index']);
    Route::get('/user/usage-goal', [UsageGoalController::class, 'showUserGoal']);
    Route::post('/user/usage-goal', [UsageGoalController::class, 'storeUserGoal']);
    Route::get('/groups/{group}/usage-goal', [UsageGoalController::class, 'showGroupGoal']);
    Route::post('/groups/{group}/usage-goal', [UsageGoalController::class, 'storeGroupGoal']);
});

Route::get('/status', static fn () => [
    'voltage_rms' => 228.12,
    'current_rms' => 0.450,
    'temperature' => 28.5,
    'power' => 102.3,
    'energy' => (int) Carbon::now()->format('H') + (int) Carbon::now()->format('i') + (int) Carbon::now()->format('s'), // Accumulated energy in kWh
    'cost' => 11.84,
    'relay_state' => true,
]);

Route::get('/on', static fn () => []);
Route::get('/off', static fn () => []);
