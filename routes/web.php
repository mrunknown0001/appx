<?php

use Illuminate\Support\Facades\Route;

// Redirect / to /app
Route::get('/', function () {
    return redirect('/app');
});
