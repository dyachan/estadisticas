<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\MatchController;

Route::post('/play', [MatchController::class, 'play']);