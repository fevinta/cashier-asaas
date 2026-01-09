<?php

declare(strict_types=1);

use Fevinta\CashierAsaas\Enums\CheckoutStatus;

test('enum has correct values', function () {
    expect(CheckoutStatus::ACTIVE->value)->toBe('ACTIVE');
    expect(CheckoutStatus::PAID->value)->toBe('PAID');
    expect(CheckoutStatus::CANCELED->value)->toBe('CANCELED');
    expect(CheckoutStatus::EXPIRED->value)->toBe('EXPIRED');
});

test('label returns human readable name', function () {
    expect(CheckoutStatus::ACTIVE->label())->toBe('Ativo');
    expect(CheckoutStatus::PAID->label())->toBe('Pago');
    expect(CheckoutStatus::CANCELED->label())->toBe('Cancelado');
    expect(CheckoutStatus::EXPIRED->label())->toBe('Expirado');
});

test('isActive returns true only for ACTIVE', function () {
    expect(CheckoutStatus::ACTIVE->isActive())->toBeTrue();
    expect(CheckoutStatus::PAID->isActive())->toBeFalse();
    expect(CheckoutStatus::CANCELED->isActive())->toBeFalse();
    expect(CheckoutStatus::EXPIRED->isActive())->toBeFalse();
});

test('isPaid returns true only for PAID', function () {
    expect(CheckoutStatus::ACTIVE->isPaid())->toBeFalse();
    expect(CheckoutStatus::PAID->isPaid())->toBeTrue();
    expect(CheckoutStatus::CANCELED->isPaid())->toBeFalse();
    expect(CheckoutStatus::EXPIRED->isPaid())->toBeFalse();
});

test('isCanceled returns true only for CANCELED', function () {
    expect(CheckoutStatus::ACTIVE->isCanceled())->toBeFalse();
    expect(CheckoutStatus::PAID->isCanceled())->toBeFalse();
    expect(CheckoutStatus::CANCELED->isCanceled())->toBeTrue();
    expect(CheckoutStatus::EXPIRED->isCanceled())->toBeFalse();
});

test('isExpired returns true only for EXPIRED', function () {
    expect(CheckoutStatus::ACTIVE->isExpired())->toBeFalse();
    expect(CheckoutStatus::PAID->isExpired())->toBeFalse();
    expect(CheckoutStatus::CANCELED->isExpired())->toBeFalse();
    expect(CheckoutStatus::EXPIRED->isExpired())->toBeTrue();
});

test('isFinished returns true for PAID, CANCELED, EXPIRED', function () {
    expect(CheckoutStatus::ACTIVE->isFinished())->toBeFalse();
    expect(CheckoutStatus::PAID->isFinished())->toBeTrue();
    expect(CheckoutStatus::CANCELED->isFinished())->toBeTrue();
    expect(CheckoutStatus::EXPIRED->isFinished())->toBeTrue();
});

test('can be created from string', function () {
    expect(CheckoutStatus::from('ACTIVE'))->toBe(CheckoutStatus::ACTIVE);
    expect(CheckoutStatus::from('PAID'))->toBe(CheckoutStatus::PAID);
    expect(CheckoutStatus::from('CANCELED'))->toBe(CheckoutStatus::CANCELED);
    expect(CheckoutStatus::from('EXPIRED'))->toBe(CheckoutStatus::EXPIRED);
});

test('tryFrom returns null for invalid value', function () {
    expect(CheckoutStatus::tryFrom('INVALID'))->toBeNull();
});
