<?php

use Monolog\Handler\NullHandler;
use Monolog\Handler\StreamHandler;
use Monolog\Processor\PsrLogMessageProcessor;

// Permissão dos arquivos de log. No Docker usamos LOG_FILE_PERMISSION=0666 para que um log
// criado por um comando executado como root continue gravável pelo PHP-FPM (www-data).
$filePermission = env('LOG_FILE_PERMISSION') ? octdec(env('LOG_FILE_PERMISSION')) : null;

return [

    'default' => env('LOG_CHANNEL', 'stack'),

    'deprecations' => [
        'channel' => env('LOG_DEPRECATIONS_CHANNEL', 'null'),
        'trace' => env('LOG_DEPRECATIONS_TRACE', false),
    ],

    'channels' => [

        'stack' => [
            'driver' => 'stack',
            'channels' => explode(',', env('LOG_STACK', 'daily')),
            'ignore_exceptions' => false,
        ],

        'single' => [
            'driver' => 'single',
            'permission' => $filePermission,
            'path' => storage_path('logs/laravel.log'),
            'level' => env('LOG_LEVEL', 'debug'),
            'replace_placeholders' => true,
        ],

        'daily' => [
            'driver' => 'daily',
            'permission' => $filePermission,
            'path' => storage_path('logs/laravel.log'),
            'level' => env('LOG_LEVEL', 'debug'),
            'days' => env('LOG_DAILY_DAYS', 14),
            'replace_placeholders' => true,
        ],

        // Log de acesso da API (método, rota, status, tempo de resposta, usuário).
        'api' => [
            'driver' => 'daily',
            'permission' => $filePermission,
            'path' => storage_path('logs/api.log'),
            'level' => 'info',
            'days' => 30,
        ],

        // Log da rotina de importação de XML executada via CRON.
        'import' => [
            'driver' => 'daily',
            'permission' => $filePermission,
            'path' => storage_path('logs/import.log'),
            'level' => 'info',
            'days' => 30,
        ],

        'stderr' => [
            'driver' => 'monolog',
            'level' => env('LOG_LEVEL', 'debug'),
            'handler' => StreamHandler::class,
            'formatter' => env('LOG_STDERR_FORMATTER'),
            'with' => [
                'stream' => 'php://stderr',
            ],
            'processors' => [PsrLogMessageProcessor::class],
        ],

        'null' => [
            'driver' => 'monolog',
            'handler' => NullHandler::class,
        ],

        'emergency' => [
            'path' => storage_path('logs/laravel.log'),
        ],

    ],

];
