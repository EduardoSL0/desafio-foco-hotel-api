<?php

namespace App\Enums;

enum UserRole: string
{
    /** Acesso total a todos os hotéis. */
    case Admin = 'admin';

    /** Gerencia quartos, cupons e reservas do próprio hotel. */
    case Manager = 'manager';

    /** Consulta reservas e registra pagamentos do próprio hotel. */
    case Receptionist = 'receptionist';
}
