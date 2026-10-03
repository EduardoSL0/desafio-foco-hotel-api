<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

abstract class Controller
{
    /**
     * Filtro textual da query string. Listas (ex.: ?status[]=x) são ignoradas em vez de
     * virarem erro de conversão.
     */
    protected function queryText(Request $request, string $key): string
    {
        $value = $request->query($key);

        return is_string($value) ? trim($value) : '';
    }
}
