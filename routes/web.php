<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::redirect('/vps', '/storage/vps-estimate.html');

Route::redirect('/architecture', '/storage/architecture.html');
