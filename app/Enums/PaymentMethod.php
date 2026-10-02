<?php

namespace App\Enums;

/**
 * Os XMLs de origem informam apenas o código numérico do método (<Method>1</Method>).
 * O mapeamento abaixo é uma convenção deste projeto e está documentado no README.
 */
enum PaymentMethod: int
{
    case CreditCard = 1;
    case DebitCard = 2;
    case Pix = 3;
    case Cash = 4;
    case BankSlip = 5;

    public function label(): string
    {
        return match ($this) {
            self::CreditCard => 'Cartão de crédito',
            self::DebitCard => 'Cartão de débito',
            self::Pix => 'Pix',
            self::Cash => 'Dinheiro',
            self::BankSlip => 'Boleto',
        };
    }

    public function allowsInstallments(): bool
    {
        return $this === self::CreditCard;
    }
}
