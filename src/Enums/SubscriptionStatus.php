<?php

declare(strict_types=1);

namespace Fevinta\CashierAsaas\Enums;

enum SubscriptionStatus: string
{
    case ACTIVE = 'ACTIVE';
    case INACTIVE = 'INACTIVE';
    case EXPIRED = 'EXPIRED';

    public function label(): string
    {
        return match ($this) {
            self::ACTIVE => 'Ativa',
            self::INACTIVE => 'Inativa',
            self::EXPIRED => 'Expirada',
        };
    }

    public function isActive(): bool
    {
        return $this === self::ACTIVE;
    }
}
