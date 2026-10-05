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

    /*
    |--------------------------------------------------------------------------
    | Limites de requisição (por minuto)
    |--------------------------------------------------------------------------
    |
    | Cada grupo de rotas tem o próprio contador (ver AppServiceProvider).
    |
    */

    'rate_limits' => [
        'login' => (int) env('RATE_LIMIT_LOGIN', 10),
        'login_per_email' => (int) env('RATE_LIMIT_LOGIN_PER_EMAIL', 5),
        'lookup' => (int) env('RATE_LIMIT_LOOKUP', 10),
        'booking' => (int) env('RATE_LIMIT_BOOKING', 30),
        'public' => (int) env('RATE_LIMIT_PUBLIC', 120),
        'staff' => (int) env('RATE_LIMIT_STAFF', 240),
    ],

    'auth' => [
        'token_ttl_hours' => (int) env('API_TOKEN_TTL_HOURS', 8),
    ],

    /*
    |--------------------------------------------------------------------------
    | Pré-reservas online
    |--------------------------------------------------------------------------
    |
    | Reserva feita sem login e sem pagamento ocupa o quarto por este prazo
    | (horas). Depois disso deixa de contar na disponibilidade e é cancelada
    | pelo comando agendado "reserves:expire". Registrar um pagamento garante a
    | reserva. 0 desativa a expiração.
    |
    */

    'reservations' => [
        'pending_ttl_hours' => (int) env('RESERVE_PENDING_TTL_HOURS', 24),
    ],

];
