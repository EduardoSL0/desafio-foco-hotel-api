<?php

/*
|--------------------------------------------------------------------------
| CORS
|--------------------------------------------------------------------------
|
| A API usa token Bearer (sem cookies), por isso supports_credentials fica
| desligado. Em produção, restrinja as origens com CORS_ALLOWED_ORIGINS
| (ex.: "https://reservas.hotel.com.br,https://painel.hotel.com.br").
|
*/

return [

    'paths' => ['api/*'],

    'allowed_methods' => ['*'],

    'allowed_origins' => array_map('trim', explode(',', (string) env('CORS_ALLOWED_ORIGINS', '*'))),

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => ['X-Request-Id', 'Idempotent-Replayed', 'Retry-After'],

    'max_age' => 0,

    'supports_credentials' => false,

];
