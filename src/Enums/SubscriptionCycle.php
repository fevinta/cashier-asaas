<?php

declare(strict_types=1);

namespace Fevinta\CashierAsaas\Enums;

enum SubscriptionCycle: string
{
    case WEEKLY = 'WEEKLY';
    case BIWEEKLY = 'BIWEEKLY';
    case MONTHLY = 'MONTHLY';
    case QUARTERLY = 'QUARTERLY';
    case SEMIANNUALLY = 'SEMIANNUALLY';
    case YEARLY = 'YEARLY';

    public function label(): string
    {
        return match ($this) {
            self::WEEKLY => 'Semanal',
            self::BIWEEKLY => 'Quinzenal',
            self::MONTHLY => 'Mensal',
            self::QUARTERLY => 'Trimestral',
            self::SEMIANNUALLY => 'Semestral',
            self::YEARLY => 'Anual',
        };
    }

    public function days(): int
    {
        return match ($this) {
            self::WEEKLY => 7,
            self::BIWEEKLY => 14,
            self::MONTHLY => 30,
            self::QUARTERLY => 90,
            self::SEMIANNUALLY => 180,
            self::YEARLY => 365,
        };
    }
}
