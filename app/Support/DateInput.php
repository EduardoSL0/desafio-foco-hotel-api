<?php

namespace App\Support;

/**
 * Regras de comparação entre datas (ex.: "after:check_in") só podem ser aplicadas
 * quando o campo de referência é uma data válida. Se o cliente enviar uma lista ou
 * um número no lugar da data, o validador do Laravel quebra ao tentar compará-los
 * (erro 500). Com estes auxiliares, o erro vira uma resposta 422 normal.
 */
final class DateInput
{
    public static function isDate(mixed $value): bool
    {
        if (! is_string($value) || ! preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $value, $m)) {
            return false;
        }

        return checkdate((int) $m[2], (int) $m[3], (int) $m[1]);
    }

    /**
     * Regra de comparação com outro campo, ou nenhuma regra se o outro campo não for uma data válida
     * (nesse caso o próprio outro campo já falha na validação de formato).
     *
     * @return list<string>
     */
    public static function compareWith(string $rule, string $field, mixed $value): array
    {
        return self::isDate($value) ? ["{$rule}:{$field}"] : [];
    }
}
