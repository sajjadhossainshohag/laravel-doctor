<?php

use Illuminate\Support\Facades\Route;

Route::get('/a', function () {
    return 'a';
});

Route::post('/b', function () {
    return 'b';
});

Route::put('/c', function () {
    return 'c';
});
