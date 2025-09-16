<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('weather');
});
Route::get('/welcome', function () {
    return view('welcome');
});
Route::get('/docs', function () {
    return redirect('/openapi.html');
});
