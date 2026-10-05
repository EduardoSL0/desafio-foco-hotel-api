<?php

use Illuminate\Support\Facades\Route;

Route::redirect('/', '/docs/index.html');

// Links montados a partir de APP_URL (e não do cabeçalho Host enviado pelo cliente).
Route::get('/api', function () {
    $base = rtrim((string) config('app.url'), '/');

    return response()->json([
        'name' => config('app.name'),
        'docs' => "{$base}/docs/",
        'openapi' => "{$base}/docs/openapi.yaml",
        'api' => "{$base}/api/v1",
        'health' => "{$base}/up",
    ]);
});
