<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/admin/{any?}', function () {
    $path = public_path('admin/index.html');
    if (!file_exists($path)) {
        abort(404, 'Admin panel not built.');
    }
    return response()->file($path);
})->where('any', '.*');
