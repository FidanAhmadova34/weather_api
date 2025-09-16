<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\WeatherController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\CityController;

Route::middleware('throttle:60,1')->group(function () {
    Route::get('/weather/current', [WeatherController::class, 'current']);
    Route::get('/weather/forecast', [WeatherController::class, 'forecast']);
    Route::get('/weather/search', [WeatherController::class, 'search']);
    Route::get('/health', function () {
        $ok = (bool) env('OPENWEATHER_API_KEY');
        return response()->json([
            'ok' => $ok,
            'time' => now()->toISOString(),
        ], $ok ? 200 : 500);
    });
});
Route::apiResource('users', UserController::class);
Route::apiResource('cities', CityController::class);
