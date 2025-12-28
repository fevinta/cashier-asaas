<?php

declare(strict_types=1);

namespace Fevinta\CashierAsaas\Enums;

enum BillingType: string
{
    case BOLETO = 'BOLETO';
    case CREDIT_CARD = 'CREDIT_CARD';
    case PIX = 'PIX';
    case UNDEFINED = 'UNDEFINED'; // Let customer choose

    public function label(): string
    {
        return match ($this) {
            self::BOLETO => 'Boleto Bancário',
            self::CREDIT_CARD => 'Cartão de Crédito',
            self::PIX => 'PIX',
            self::UNDEFINED => 'Cliente escolhe',
        };
    }
}
