<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\MatchController;
use App\Http\Controllers\TeamController;

Route::post('/play', [MatchController::class, 'play']);
Route::get('/getteams', [TeamController::class, 'get']);
Route::post('/storeteam', [TeamController::class, 'store']);