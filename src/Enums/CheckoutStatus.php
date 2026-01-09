<?php

declare(strict_types=1);

namespace Fevinta\CashierAsaas\Enums;

enum CheckoutStatus: string
{
    case ACTIVE = 'ACTIVE';
    case PAID = 'PAID';
    case CANCELED = 'CANCELED';
    case EXPIRED = 'EXPIRED';

    public function label(): string
    {
        return match ($this) {
            self::ACTIVE => 'Ativo',
            self::PAID => 'Pago',
            self::CANCELED => 'Cancelado',
            self::EXPIRED => 'Expirado',
        };
    }

    public function isActive(): bool
    {
        return $this === self::ACTIVE;
    }

    public function isPaid(): bool
    {
        return $this === self::PAID;
    }

    public function isCanceled(): bool
    {
        return $this === self::CANCELED;
    }

    public function isExpired(): bool
    {
        return $this === self::EXPIRED;
    }

    public function isFinished(): bool
    {
        return in_array($this, [self::PAID, self::CANCELED, self::EXPIRED], true);
    }
}
