<?php

declare(strict_types=1);

namespace Fevinta\CashierAsaas\Enums;

enum ChargeType: string
{
    case DETACHED = 'DETACHED';
    case INSTALLMENT = 'INSTALLMENT';
    case RECURRENT = 'RECURRENT';

    public function label(): string
    {
        return match ($this) {
            self::DETACHED => 'Pagamento Único',
            self::INSTALLMENT => 'Parcelado',
            self::RECURRENT => 'Recorrente',
        };
    }

    public function isOneTime(): bool
    {
        return $this === self::DETACHED;
    }

    public function isInstallment(): bool
    {
        return $this === self::INSTALLMENT;
    }

    public function isRecurrent(): bool
    {
        return $this === self::RECURRENT;
    }
}
