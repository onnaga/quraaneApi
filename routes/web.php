<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\MediaController;

Route::get('/media/{folder}/{filename}', [MediaController::class, 'show'])
    ->where('folder', '[A-Za-z0-9_]+')
    ->where('filename', '[A-Za-z0-9_\-\.]+');