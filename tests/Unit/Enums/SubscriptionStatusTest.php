<?php

declare(strict_types=1);

use Fevinta\CashierAsaas\Enums\SubscriptionStatus;

test('subscription status has correct values', function () {
    expect(SubscriptionStatus::ACTIVE->value)->toBe('ACTIVE');
    expect(SubscriptionStatus::INACTIVE->value)->toBe('INACTIVE');
    expect(SubscriptionStatus::EXPIRED->value)->toBe('EXPIRED');
});

test('subscription status has labels', function () {
    expect(SubscriptionStatus::ACTIVE->label())->toBe('Ativa');
    expect(SubscriptionStatus::INACTIVE->label())->toBe('Inativa');
    expect(SubscriptionStatus::EXPIRED->label())->toBe('Expirada');
});

test('subscription status isActive check', function () {
    expect(SubscriptionStatus::ACTIVE->isActive())->toBeTrue();
    expect(SubscriptionStatus::INACTIVE->isActive())->toBeFalse();
    expect(SubscriptionStatus::EXPIRED->isActive())->toBeFalse();
});

test('all subscription statuses are enumerable', function () {
    $cases = SubscriptionStatus::cases();

    expect($cases)->toHaveCount(3);
    expect($cases)->toContain(SubscriptionStatus::ACTIVE);
    expect($cases)->toContain(SubscriptionStatus::INACTIVE);
    expect($cases)->toContain(SubscriptionStatus::EXPIRED);
});

test('subscription status can be created from value', function () {
    expect(SubscriptionStatus::from('ACTIVE'))->toBe(SubscriptionStatus::ACTIVE);
    expect(SubscriptionStatus::from('INACTIVE'))->toBe(SubscriptionStatus::INACTIVE);
    expect(SubscriptionStatus::from('EXPIRED'))->toBe(SubscriptionStatus::EXPIRED);
});

test('subscription status tryFrom returns null for invalid value', function () {
    expect(SubscriptionStatus::tryFrom('CANCELLED'))->toBeNull();
});
