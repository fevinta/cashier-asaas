<?php

declare(strict_types=1);

use Fevinta\CashierAsaas\Enums\BillingType;

test('billing type has correct values', function () {
    expect(BillingType::BOLETO->value)->toBe('BOLETO');
    expect(BillingType::CREDIT_CARD->value)->toBe('CREDIT_CARD');
    expect(BillingType::PIX->value)->toBe('PIX');
    expect(BillingType::UNDEFINED->value)->toBe('UNDEFINED');
});

test('billing type has labels', function () {
    expect(BillingType::BOLETO->label())->toBe('Boleto Bancário');
    expect(BillingType::CREDIT_CARD->label())->toBe('Cartão de Crédito');
    expect(BillingType::PIX->label())->toBe('PIX');
    expect(BillingType::UNDEFINED->label())->toBe('Cliente escolhe');
});

test('all billing types are enumerable', function () {
    $cases = BillingType::cases();

    expect($cases)->toHaveCount(4);
    expect($cases)->toContain(BillingType::BOLETO);
    expect($cases)->toContain(BillingType::CREDIT_CARD);
    expect($cases)->toContain(BillingType::PIX);
    expect($cases)->toContain(BillingType::UNDEFINED);
});

test('billing type can be created from value', function () {
    expect(BillingType::from('BOLETO'))->toBe(BillingType::BOLETO);
    expect(BillingType::from('CREDIT_CARD'))->toBe(BillingType::CREDIT_CARD);
    expect(BillingType::from('PIX'))->toBe(BillingType::PIX);
    expect(BillingType::from('UNDEFINED'))->toBe(BillingType::UNDEFINED);
});

test('billing type tryFrom returns null for invalid value', function () {
    expect(BillingType::tryFrom('INVALID'))->toBeNull();
});
