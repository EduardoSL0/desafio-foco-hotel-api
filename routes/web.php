<?php

use Illuminate\Support\Facades\Route;

Route::redirect('/', '/docs/');

Route::get('/api', fn () => response()->json([
    'name' => config('app.name'),
    'docs' => url('/docs/'),
    'openapi' => url('/docs/openapi.yaml'),
    'api' => url('/api/v1'),
    'health' => url('/up'),
]));
