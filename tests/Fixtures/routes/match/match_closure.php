<?php

use Illuminate\Support\Facades\Route;

Route::match(['get', 'post'], '/submit', function () {
    return 'submitted';
});
