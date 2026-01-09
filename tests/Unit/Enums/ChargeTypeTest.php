<?php

declare(strict_types=1);

use Fevinta\CashierAsaas\Enums\ChargeType;

test('enum has correct values', function () {
    expect(ChargeType::DETACHED->value)->toBe('DETACHED');
    expect(ChargeType::INSTALLMENT->value)->toBe('INSTALLMENT');
    expect(ChargeType::RECURRENT->value)->toBe('RECURRENT');
});

test('label returns human readable name', function () {
    expect(ChargeType::DETACHED->label())->toBe('Pagamento Único');
    expect(ChargeType::INSTALLMENT->label())->toBe('Parcelado');
    expect(ChargeType::RECURRENT->label())->toBe('Recorrente');
});

test('isOneTime returns true only for DETACHED', function () {
    expect(ChargeType::DETACHED->isOneTime())->toBeTrue();
    expect(ChargeType::INSTALLMENT->isOneTime())->toBeFalse();
    expect(ChargeType::RECURRENT->isOneTime())->toBeFalse();
});

test('isInstallment returns true only for INSTALLMENT', function () {
    expect(ChargeType::DETACHED->isInstallment())->toBeFalse();
    expect(ChargeType::INSTALLMENT->isInstallment())->toBeTrue();
    expect(ChargeType::RECURRENT->isInstallment())->toBeFalse();
});

test('isRecurrent returns true only for RECURRENT', function () {
    expect(ChargeType::DETACHED->isRecurrent())->toBeFalse();
    expect(ChargeType::INSTALLMENT->isRecurrent())->toBeFalse();
    expect(ChargeType::RECURRENT->isRecurrent())->toBeTrue();
});

test('can be created from string', function () {
    expect(ChargeType::from('DETACHED'))->toBe(ChargeType::DETACHED);
    expect(ChargeType::from('INSTALLMENT'))->toBe(ChargeType::INSTALLMENT);
    expect(ChargeType::from('RECURRENT'))->toBe(ChargeType::RECURRENT);
});

test('tryFrom returns null for invalid value', function () {
    expect(ChargeType::tryFrom('INVALID'))->toBeNull();
});
