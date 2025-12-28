<?php

declare(strict_types=1);

use Fevinta\CashierAsaas\Enums\SubscriptionCycle;

test('subscription cycle has correct values', function () {
    expect(SubscriptionCycle::WEEKLY->value)->toBe('WEEKLY');
    expect(SubscriptionCycle::BIWEEKLY->value)->toBe('BIWEEKLY');
    expect(SubscriptionCycle::MONTHLY->value)->toBe('MONTHLY');
    expect(SubscriptionCycle::QUARTERLY->value)->toBe('QUARTERLY');
    expect(SubscriptionCycle::SEMIANNUALLY->value)->toBe('SEMIANNUALLY');
    expect(SubscriptionCycle::YEARLY->value)->toBe('YEARLY');
});

test('subscription cycle has labels', function () {
    expect(SubscriptionCycle::WEEKLY->label())->toBe('Semanal');
    expect(SubscriptionCycle::BIWEEKLY->label())->toBe('Quinzenal');
    expect(SubscriptionCycle::MONTHLY->label())->toBe('Mensal');
    expect(SubscriptionCycle::QUARTERLY->label())->toBe('Trimestral');
    expect(SubscriptionCycle::SEMIANNUALLY->label())->toBe('Semestral');
    expect(SubscriptionCycle::YEARLY->label())->toBe('Anual');
});

test('subscription cycle has days', function () {
    expect(SubscriptionCycle::WEEKLY->days())->toBe(7);
    expect(SubscriptionCycle::BIWEEKLY->days())->toBe(14);
    expect(SubscriptionCycle::MONTHLY->days())->toBe(30);
    expect(SubscriptionCycle::QUARTERLY->days())->toBe(90);
    expect(SubscriptionCycle::SEMIANNUALLY->days())->toBe(180);
    expect(SubscriptionCycle::YEARLY->days())->toBe(365);
});

test('all subscription cycles are enumerable', function () {
    $cases = SubscriptionCycle::cases();

    expect($cases)->toHaveCount(6);
    expect($cases)->toContain(SubscriptionCycle::WEEKLY);
    expect($cases)->toContain(SubscriptionCycle::BIWEEKLY);
    expect($cases)->toContain(SubscriptionCycle::MONTHLY);
    expect($cases)->toContain(SubscriptionCycle::QUARTERLY);
    expect($cases)->toContain(SubscriptionCycle::SEMIANNUALLY);
    expect($cases)->toContain(SubscriptionCycle::YEARLY);
});

test('subscription cycle can be created from value', function () {
    expect(SubscriptionCycle::from('MONTHLY'))->toBe(SubscriptionCycle::MONTHLY);
    expect(SubscriptionCycle::from('YEARLY'))->toBe(SubscriptionCycle::YEARLY);
});

test('subscription cycle tryFrom returns null for invalid value', function () {
    expect(SubscriptionCycle::tryFrom('DAILY'))->toBeNull();
});
