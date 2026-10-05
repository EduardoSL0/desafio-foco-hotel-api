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

    /**
     * Divide um valor (em centavos) proporcionalmente aos pesos informados, sem perder
     * centavos: a soma das partes é sempre igual ao valor (maiores restos recebem o centavo).
     *
     * @param  array<array-key, int>  $weights
     * @return array<array-key, int>
     */
    public static function allocate(int $amount, array $weights): array
    {
        $total = array_sum($weights);

        if ($amount <= 0 || $total <= 0) {
            return array_map(fn () => 0, $weights);
        }

        $shares = [];
        $remainders = [];

        foreach ($weights as $key => $weight) {
            $exact = $amount * $weight / $total;
            $shares[$key] = (int) floor($exact);
            $remainders[$key] = $exact - $shares[$key];
        }

        arsort($remainders);

        foreach (array_slice(array_keys($remainders), 0, $amount - array_sum($shares)) as $key) {
            $shares[$key]++;
        }

        return $shares;
    }
}
