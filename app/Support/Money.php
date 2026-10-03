<?php

namespace App\Support;

/**
 * Cálculos monetários são feitos em centavos (inteiros) para evitar erros de
 * arredondamento de ponto flutuante. A conversão para decimal acontece apenas
 * na persistência e na resposta da API.
 */
final class Money
{
    /** Maior valor que cabe nas colunas decimal(10,2): R$ 99.999.999,99. */
    public const MAX_CENTS = 9_999_999_999;

    public static function toCents(string|int|float|null $value): int
    {
        return (int) round(((float) $value) * 100);
    }

    public static function fromCents(int $cents): float
    {
        return round($cents / 100, 2);
    }

    public static function percentOf(int $cents, string|int|float $percent): int
    {
        return (int) round($cents * ((float) $percent) / 100);
    }
}
