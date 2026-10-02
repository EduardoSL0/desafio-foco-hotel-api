<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Base dos resources da API. Mantém o tipo float em valores decimais
 * (100.0 em vez de 100), deixando os campos monetários com tipo consistente.
 * Coleções (Resource::collection) herdam a opção do resource que agrupam.
 */
abstract class ApiResource extends JsonResource
{
    public const JSON_OPTIONS = JSON_PRESERVE_ZERO_FRACTION;

    public function jsonOptions(): int
    {
        return self::JSON_OPTIONS;
    }
}
