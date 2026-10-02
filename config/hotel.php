<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Importação de XML
    |--------------------------------------------------------------------------
    |
    | Diretório onde ficam os arquivos hotels.xml, rooms.xml e reserves.xml e a
    | expressão CRON usada pelo scheduler do Laravel para disparar o comando
    | "import:xml". A ordem dos arquivos importa (hotéis -> quartos -> reservas).
    |
    */

    'import' => [
        'path' => env('XML_IMPORT_PATH') ?: database_path('xml'),
        'schedule' => env('XML_IMPORT_CRON', '0 * * * *'),
        'files' => [
            'hotels' => 'hotels.xml',
            'rooms' => 'rooms.xml',
            'reserves' => 'reserves.xml',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Pagamentos
    |--------------------------------------------------------------------------
    |
    | Parcelamentos acima de "interest_free_installments" sofrem juros simples
    | de "monthly_interest_percent" por parcela excedente.
    |
    */

    'payments' => [
        'interest_free_installments' => (int) env('PAYMENT_INTEREST_FREE_INSTALLMENTS', 3),
        'monthly_interest_percent' => (float) env('PAYMENT_MONTHLY_INTEREST_PERCENT', 1.99),
        'max_installments' => 12,
    ],

    'auth' => [
        'token_ttl_hours' => (int) env('API_TOKEN_TTL_HOURS', 8),
    ],

];
