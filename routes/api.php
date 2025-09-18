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
    // v1 DTO routes
    Route::prefix('v1')->group(function () {
        Route::get('/weather/current', [WeatherController::class, 'currentV1']);
        Route::get('/weather/forecast', [WeatherController::class, 'forecastV1']);
    });
});
Route::apiResource('users', UserController::class);
Route::apiResource('cities', CityController::class);
Route::middleware(['auth:sanctum'])->group(function () {

    // Everyone logged in can view today's weather
    Route::get('/weather/today', [WeatherController::class, 'today'])
         ->middleware('can:view-today');

    // Only premium & admin
    Route::get('/weather/history', [WeatherController::class, 'history'])
         ->middleware('can:view-history');

    // Only premium & admin
    Route::post('/weather/favorite', [WeatherController::class, 'storeFavorite'])
         ->middleware('can:save-favorite');

    // Only admin
    Route::delete('/weather/favorite/{id}', [WeatherController::class, 'deleteFavorite'])
         ->middleware('can:delete-favorite');
});
