<?php

/*
 * Sobrescreve apenas as chaves abaixo; o restante vem da configuração padrão
 * do framework (mesclada automaticamente pelo Laravel 11+).
 */
return [

    'timezone' => env('APP_TIMEZONE', 'America/Bahia'),

    'locale' => env('APP_LOCALE', 'pt_BR'),

    'fallback_locale' => env('APP_FALLBACK_LOCALE', 'en'),

];
