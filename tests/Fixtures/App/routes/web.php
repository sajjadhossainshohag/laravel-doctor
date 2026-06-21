<?php

use Illuminate\Support\Facades\Route;

Route::get('/test', function () {
    return 'ok';
})->middleware('non.existent.alias');
