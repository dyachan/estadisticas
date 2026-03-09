<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\GameFormationController;
use App\Http\Controllers\GamePlayerController;
use App\Http\Controllers\GameTacticController;
use App\Http\Controllers\MatchController;
use App\Http\Controllers\SimulationController;
use App\Http\Controllers\TeamController;
use App\Http\Controllers\RoguelikeController;
use App\Http\Controllers\UserController;

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
Route::get('/match/{match}/replay',      [MatchController::class, 'replay']);

// Game mode
Route::post('/users',                    [UserController::class, 'store']);
Route::post('/game/players',             [GamePlayerController::class, 'store']);
Route::put('/game/players/{player}',     [GamePlayerController::class, 'update']);
Route::post('/game/formation',           [GameFormationController::class, 'upsert']);
Route::post('/game/tactics',             [GameTacticController::class, 'store']);

// Roguelike
Route::post('/roguelike/start',          [RoguelikeController::class, 'start']);
Route::post('/roguelike/play',           [RoguelikeController::class, 'play']);