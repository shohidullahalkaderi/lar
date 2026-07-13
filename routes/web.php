<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return response()->json([
        'Status' => 'Healthy.',
        'Message' => 'Dockerize Laravel Backend Rest API Development.'
    ]);
});
