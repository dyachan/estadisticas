<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\MatchController;

Route::get('/', function () {
    return view('test');
});

Route::get('/test', function () {
    return 'test';
});

