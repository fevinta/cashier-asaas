<?php

declare(strict_types=1);

use Fevinta\CashierAsaas\Asaas;

beforeEach(function () {
    skipIfNoAsaasCredentials();

    // Ensure sandbox mode is enabled
    Asaas::useSandbox(true);
});

test('can create a customer in asaas sandbox', function () {
    $customer = Asaas::customer()->create([
        'name' => 'Integration Test User '.uniqid(),
        'email' => 'test_'.uniqid().'@example.com',
        'cpfCnpj' => generateTestCpf(),
        'phone' => '11999999999',
        'externalReference' => 'integration_test_'.uniqid(),
    ]);

    expect($customer)->toBeArray();
    expect($customer['id'])->toStartWith('cus_');
    expect($customer['name'])->toContain('Integration Test User');

    // Cleanup: delete the customer
    Asaas::customer()->delete($customer['id']);
});

test('can retrieve a customer from asaas sandbox', function () {
    // Create a customer first
    $created = Asaas::customer()->create([
        'name' => 'Retrieve Test User',
        'email' => 'retrieve_'.uniqid().'@example.com',
        'cpfCnpj' => generateTestCpf(),
    ]);

    $customer = Asaas::customer()->find($created['id']);

    expect($customer['id'])->toBe($created['id']);
    expect($customer['name'])->toBe('Retrieve Test User');

    // Cleanup
    Asaas::customer()->delete($created['id']);
});

test('can update a customer in asaas sandbox', function () {
    // Create a customer first
    $created = Asaas::customer()->create([
        'name' => 'Original Name',
        'email' => 'update_'.uniqid().'@example.com',
        'cpfCnpj' => generateTestCpf(),
    ]);

    $updated = Asaas::customer()->update($created['id'], [
        'name' => 'Updated Name',
    ]);

    expect($updated['name'])->toBe('Updated Name');

    // Cleanup
    Asaas::customer()->delete($created['id']);
});

test('can delete a customer from asaas sandbox', function () {
    // Create a customer first
    $created = Asaas::customer()->create([
        'name' => 'Delete Test User',
        'email' => 'delete_'.uniqid().'@example.com',
        'cpfCnpj' => generateTestCpf(),
    ]);

    $result = Asaas::customer()->delete($created['id']);

    expect($result)->toBeTrue();
});

test('can create a pix payment in asaas sandbox', function () {
    // Create a customer first
    $customer = Asaas::customer()->create([
        'name' => 'PIX Payment Test',
        'email' => 'pix_'.uniqid().'@example.com',
        'cpfCnpj' => generateTestCpf(),
    ]);

    $payment = Asaas::payment()->create([
        'customer' => $customer['id'],
        'billingType' => 'PIX',
        'value' => 10.00,
        'dueDate' => now()->addDays(3)->format('Y-m-d'),
        'description' => 'Integration test PIX payment',
    ]);

    expect($payment)->toBeArray();
    expect($payment['id'])->toStartWith('pay_');
    expect($payment['billingType'])->toBe('PIX');
    expect((float) $payment['value'])->toBe(10.00);

    // Cleanup
    Asaas::payment()->delete($payment['id']);
    Asaas::customer()->delete($customer['id']);
});

test('can create a boleto payment in asaas sandbox', function () {
    // Create a customer first
    $customer = Asaas::customer()->create([
        'name' => 'Boleto Payment Test',
        'email' => 'boleto_'.uniqid().'@example.com',
        'cpfCnpj' => generateTestCpf(),
    ]);

    $payment = Asaas::payment()->create([
        'customer' => $customer['id'],
        'billingType' => 'BOLETO',
        'value' => 25.00,
        'dueDate' => now()->addDays(5)->format('Y-m-d'),
        'description' => 'Integration test Boleto payment',
    ]);

    expect($payment)->toBeArray();
    expect($payment['id'])->toStartWith('pay_');
    expect($payment['billingType'])->toBe('BOLETO');
    expect($payment['bankSlipUrl'])->not->toBeNull();

    // Cleanup
    Asaas::payment()->delete($payment['id']);
    Asaas::customer()->delete($customer['id']);
});

test('can get pix qr code from asaas sandbox', function () {
    // Create a customer first
    $customer = Asaas::customer()->create([
        'name' => 'PIX QR Test',
        'email' => 'pixqr_'.uniqid().'@example.com',
        'cpfCnpj' => generateTestCpf(),
    ]);

    // Create a PIX payment
    $payment = Asaas::payment()->create([
        'customer' => $customer['id'],
        'billingType' => 'PIX',
        'value' => 15.00,
        'dueDate' => now()->addDays(1)->format('Y-m-d'),
    ]);

    $qrCode = Asaas::payment()->pixQrCode($payment['id']);

    expect($qrCode)->toBeArray();
    expect($qrCode['encodedImage'])->not->toBeNull();
    expect($qrCode['payload'])->not->toBeNull();

    // Cleanup
    Asaas::payment()->delete($payment['id']);
    Asaas::customer()->delete($customer['id']);
});

test('can create a subscription in asaas sandbox', function () {
    // Create a customer first
    $customer = Asaas::customer()->create([
        'name' => 'Subscription Test',
        'email' => 'subscription_'.uniqid().'@example.com',
        'cpfCnpj' => generateTestCpf(),
    ]);

    $subscription = Asaas::subscription()->create([
        'customer' => $customer['id'],
        'billingType' => 'PIX',
        'value' => 49.90,
        'nextDueDate' => now()->addMonth()->format('Y-m-d'),
        'cycle' => 'MONTHLY',
        'description' => 'Integration test subscription',
    ]);

    expect($subscription)->toBeArray();
    expect($subscription['id'])->toStartWith('sub_');
    expect($subscription['cycle'])->toBe('MONTHLY');
    expect((float) $subscription['value'])->toBe(49.90);

    // Cleanup
    Asaas::subscription()->delete($subscription['id']);
    Asaas::customer()->delete($customer['id']);
});

test('can cancel a subscription in asaas sandbox', function () {
    // Create a customer first
    $customer = Asaas::customer()->create([
        'name' => 'Cancel Sub Test',
        'email' => 'cancel_'.uniqid().'@example.com',
        'cpfCnpj' => generateTestCpf(),
    ]);

    // Create a subscription
    $subscription = Asaas::subscription()->create([
        'customer' => $customer['id'],
        'billingType' => 'PIX',
        'value' => 29.90,
        'nextDueDate' => now()->addMonth()->format('Y-m-d'),
        'cycle' => 'MONTHLY',
    ]);

    // Cancel it
    $result = Asaas::subscription()->delete($subscription['id']);

    expect($result)->toBeTrue();

    // Cleanup
    Asaas::customer()->delete($customer['id']);
});

test('can list subscription payments from asaas sandbox', function () {
    // Create a customer first
    $customer = Asaas::customer()->create([
        'name' => 'Sub Payments Test',
        'email' => 'subpay_'.uniqid().'@example.com',
        'cpfCnpj' => generateTestCpf(),
    ]);

    // Create a subscription
    $subscription = Asaas::subscription()->create([
        'customer' => $customer['id'],
        'billingType' => 'PIX',
        'value' => 19.90,
        'nextDueDate' => now()->format('Y-m-d'), // Start immediately
        'cycle' => 'MONTHLY',
    ]);

    // Get payments
    $payments = Asaas::subscription()->payments($subscription['id']);

    expect($payments)->toBeArray();
    expect($payments)->toHaveKey('data');

    // Cleanup
    Asaas::subscription()->delete($subscription['id']);
    Asaas::customer()->delete($customer['id']);
});
