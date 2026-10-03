<?php

/*
|--------------------------------------------------------------------------
| Mensagens de validação em português
|--------------------------------------------------------------------------
|
| Cobre as regras usadas pela API. O nome de cada campo vem de "attributes".
|
*/

return [
    'accepted' => 'O campo :attribute deve ser aceito.',
    'after' => 'O campo :attribute deve ser uma data posterior a :date.',
    'after_or_equal' => 'O campo :attribute deve ser uma data igual ou posterior a :date.',
    'alpha_dash' => 'O campo :attribute deve conter apenas letras, números, hífens e sublinhados.',
    'alpha_num' => 'O campo :attribute deve conter apenas letras e números.',
    'array' => 'O campo :attribute deve ser uma lista.',
    'before' => 'O campo :attribute deve ser uma data anterior a :date.',
    'before_or_equal' => 'O campo :attribute deve ser uma data igual ou anterior a :date.',
    'between' => [
        'array' => 'O campo :attribute deve ter entre :min e :max itens.',
        'numeric' => 'O campo :attribute deve estar entre :min e :max.',
        'string' => 'O campo :attribute deve ter entre :min e :max caracteres.',
    ],
    'boolean' => 'O campo :attribute deve ser verdadeiro ou falso.',
    'date' => 'O campo :attribute deve ser uma data válida.',
    'date_format' => 'O campo :attribute deve estar no formato :format (ex.: 2030-12-31).',
    'decimal' => 'O campo :attribute deve ter :decimal casas decimais.',
    'email' => 'O campo :attribute deve ser um e-mail válido.',
    'enum' => 'O valor informado em :attribute é inválido.',
    'exists' => 'O :attribute informado não existe.',
    'in' => 'O valor informado em :attribute é inválido.',
    'integer' => 'O campo :attribute deve ser um número inteiro.',
    'max' => [
        'array' => 'O campo :attribute não pode ter mais de :max itens.',
        'numeric' => 'O campo :attribute não pode ser maior que :max.',
        'string' => 'O campo :attribute não pode ter mais de :max caracteres.',
    ],
    'min' => [
        'array' => 'O campo :attribute deve ter pelo menos :min item(ns).',
        'numeric' => 'O campo :attribute deve ser no mínimo :min.',
        'string' => 'O campo :attribute deve ter pelo menos :min caracteres.',
    ],
    'numeric' => 'O campo :attribute deve ser um número.',
    'password' => [
        'letters' => 'O campo :attribute deve conter pelo menos uma letra.',
        'mixed' => 'O campo :attribute deve conter letras maiúsculas e minúsculas.',
        'numbers' => 'O campo :attribute deve conter pelo menos um número.',
        'symbols' => 'O campo :attribute deve conter pelo menos um símbolo.',
        'uncompromised' => 'Esta senha apareceu em um vazamento de dados. Escolha outra.',
    ],
    'prohibited' => 'O campo :attribute não pode ser enviado.',
    'regex' => 'O formato do campo :attribute é inválido.',
    'required' => 'O campo :attribute é obrigatório.',
    'size' => [
        'array' => 'O campo :attribute deve ter :size itens.',
        'numeric' => 'O campo :attribute deve ser :size.',
        'string' => 'O campo :attribute deve ter :size caracteres.',
    ],
    'string' => 'O campo :attribute deve ser um texto.',
    'unique' => 'Já existe um registro com este :attribute.',

    'attributes' => [
        'active' => 'ativo',
        'capacity' => 'capacidade',
        'check_in' => 'check-in',
        'check_out' => 'check-out',
        'code' => 'código',
        'coupon_code' => 'cupom',
        'daily_price' => 'diária',
        'description' => 'descrição',
        'discount_percent' => 'percentual de desconto',
        'email' => 'e-mail',
        'ends_at' => 'fim',
        'from' => 'data inicial',
        'guests' => 'hóspedes',
        'guests.*.email' => 'e-mail do hóspede',
        'guests.*.last_name' => 'sobrenome do hóspede',
        'guests.*.name' => 'nome do hóspede',
        'guests.*.phone' => 'telefone do hóspede',
        'hotel_id' => 'hotel',
        'installments' => 'parcelas',
        'inventory' => 'unidades',
        'last_name' => 'sobrenome',
        'max_uses' => 'limite de usos',
        'method' => 'forma de pagamento',
        'name' => 'nome',
        'password' => 'senha',
        'payments' => 'pagamentos',
        'payments.*.installments' => 'parcelas do pagamento',
        'payments.*.method' => 'forma de pagamento',
        'payments.*.value' => 'valor do pagamento',
        'role' => 'perfil',
        'room_id' => 'quarto',
        'starts_at' => 'início',
        'to' => 'data final',
        'type' => 'tipo',
        'valid_from' => 'início da validade',
        'valid_until' => 'fim da validade',
        'value' => 'valor',
    ],
];
