<?php

use Illuminate\Support\Facades\Route;

Route::get('/proxy-image/{filename}', function ($filename) {
    $path = storage_path("app/public/uploadsDaoras/$filename");
    if (!file_exists($path)) {
        abort(404);
    }
    return response()->file($path, [
        'Access-Control-Allow-Origin' => '*',
        'Access-Control-Allow-Methods' => 'GET, OPTIONS',
        'Access-Control-Allow-Headers' => '*',
    ]);
});