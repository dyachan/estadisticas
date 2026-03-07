<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\MatchController;
use App\Http\Controllers\SimulationController;
use App\Http\Controllers\TeamController;

Route::get('/getteams',   [TeamController::class, 'get']);
Route::post('/storeteam', [TeamController::class, 'store']);
Route::post('/play',      [SimulationController::class, 'play']);

// Teams
Route::get('/teams',                     [TeamController::class, 'get']);
Route::post('/teams',                    [TeamController::class, 'store']);
Route::get('/teams/{team}',              [TeamController::class, 'show']);
Route::post('/teams/{team}/upgrade',     [TeamController::class, 'upgrade']);
Route::post('/teams/{team}/rule',        [TeamController::class, 'assignRule']);

// Matches
Route::post('/match/play',               [MatchController::class, 'play']);
Route::get('/match/history/{team}',      [MatchController::class, 'history']);