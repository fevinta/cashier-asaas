<?php

declare(strict_types=1);

use Fevinta\CashierAsaas\Asaas;
use Fevinta\CashierAsaas\Tests\Fixtures\User;

beforeEach(function () {
    skipIfNoAsaasCredentials();

    // Ensure sandbox mode is enabled
    Asaas::useSandbox(true);
});

test('complete subscription lifecycle using billable trait', function () {
    // Step 1: Create a user
    $user = User::create([
        'name' => 'Lifecycle Test User',
        'email' => 'lifecycle_'.uniqid().'@example.com',
        'cpf_cnpj' => generateTestCpf(),
        'phone' => '11999998888',
    ]);

    // Step 2: Create customer in Asaas
    $customer = $user->createAsAsaasCustomer();

    expect($customer)->toBeArray();
    expect($customer['id'])->toStartWith('cus_');
    expect($user->hasAsaasId())->toBeTrue();

    // Step 3: Create subscription
    $subscription = $user->newSubscription('default', 'premium')
        ->price(99.90)
        ->monthly()
        ->withPix()
        ->description('Integration test subscription')
        ->create();

    expect($subscription->active())->toBeTrue();
    expect($user->subscribed('default'))->toBeTrue();

    // Step 4: Verify subscription in Asaas
    $asaasSubscription = $subscription->asAsaasSubscription();
    expect($asaasSubscription['status'])->toBe('ACTIVE');
    expect((float) $asaasSubscription['value'])->toBe(99.90);

    // Step 5: Get subscription payments
    $payments = $subscription->asaasPayments();
    expect($payments)->toHaveKey('data');

    // Step 6: Cancel subscription
    $subscription->cancel();
    expect($subscription->cancelled())->toBeTrue();
    expect($subscription->onGracePeriod())->toBeTrue();

    // Subscription should still be valid during grace period
    expect($user->subscribed('default'))->toBeTrue();

    // Step 7: Cancel immediately (for cleanup)
    $subscription->cancelNow();
    expect($subscription->ended())->toBeTrue();

    // Now user should not be subscribed
    expect($user->subscribed('default'))->toBeFalse();

    // Cleanup: Delete customer
    Asaas::customer()->delete($user->asaas_id);
});

test('subscription with trial period', function () {
    $user = User::create([
        'name' => 'Trial Test User',
        'email' => 'trial_'.uniqid().'@example.com',
        'cpf_cnpj' => generateTestCpf(),
    ]);

    $user->createAsAsaasCustomer();

    $subscription = $user->newSubscription('default', 'premium')
        ->price(99.90)
        ->monthly()
        ->withPix()
        ->trialDays(14)
        ->create();

    expect($subscription->onTrial())->toBeTrue();
    expect($subscription->trial_ends_at)->not->toBeNull();
    expect($subscription->valid())->toBeTrue();

    // User should be considered on trial
    expect($user->onTrial('default'))->toBeTrue();

    // Cleanup
    $subscription->cancelNow();
    Asaas::customer()->delete($user->asaas_id);
});

test('create pix charge and check payment data', function () {
    $user = User::create([
        'name' => 'PIX Charge Test',
        'email' => 'pixcharge_'.uniqid().'@example.com',
        'cpf_cnpj' => generateTestCpf(),
    ]);

    $user->createAsAsaasCustomer();

    $payment = $user->chargeWithPix(50.00, [
        'description' => 'Integration test PIX charge',
    ]);

    expect($payment->billing_type)->toBe('PIX');
    expect($payment->value)->toBe(50.00);
    expect($payment->isPending())->toBeTrue();
    expect($payment->pix_qrcode)->not->toBeNull();
    expect($payment->pix_copy_paste)->not->toBeNull();

    // Verify payment in Asaas
    $asaasPayment = Asaas::payment()->find($payment->asaas_id);
    expect($asaasPayment['billingType'])->toBe('PIX');

    // Cleanup
    Asaas::payment()->delete($payment->asaas_id);
    Asaas::customer()->delete($user->asaas_id);
});

test('create boleto charge and check bank slip url', function () {
    $user = User::create([
        'name' => 'Boleto Charge Test',
        'email' => 'boletocharge_'.uniqid().'@example.com',
        'cpf_cnpj' => generateTestCpf(),
    ]);

    $user->createAsAsaasCustomer();

    $payment = $user->chargeWithBoleto(75.00, [
        'description' => 'Integration test Boleto charge',
    ]);

    expect($payment->billing_type)->toBe('BOLETO');
    expect($payment->value)->toBe(75.00);
    expect($payment->isPending())->toBeTrue();
    expect($payment->bank_slip_url)->not->toBeNull();
    expect($payment->paymentUrl())->not->toBeNull();

    // Cleanup
    Asaas::payment()->delete($payment->asaas_id);
    Asaas::customer()->delete($user->asaas_id);
});

test('swap subscription plan', function () {
    $user = User::create([
        'name' => 'Swap Plan Test',
        'email' => 'swap_'.uniqid().'@example.com',
        'cpf_cnpj' => generateTestCpf(),
    ]);

    $user->createAsAsaasCustomer();

    // Create basic subscription
    $subscription = $user->newSubscription('default', 'basic')
        ->price(49.90)
        ->monthly()
        ->withPix()
        ->create();

    expect($subscription->plan)->toBe('basic');
    expect($subscription->value)->toBe(49.90);

    // Swap to premium
    $subscription->swap('premium', 99.90);

    expect($subscription->plan)->toBe('premium');
    expect($subscription->value)->toBe(99.90);

    // Verify in Asaas
    $asaasSubscription = $subscription->asAsaasSubscription();
    expect((float) $asaasSubscription['value'])->toBe(99.90);

    // Cleanup
    $subscription->cancelNow();
    Asaas::customer()->delete($user->asaas_id);
});

test('update subscription value', function () {
    $user = User::create([
        'name' => 'Update Value Test',
        'email' => 'updatevalue_'.uniqid().'@example.com',
        'cpf_cnpj' => generateTestCpf(),
    ]);

    $user->createAsAsaasCustomer();

    $subscription = $user->newSubscription('default', 'premium')
        ->price(99.90)
        ->monthly()
        ->withPix()
        ->create();

    // Update value
    $subscription->updateValue(149.90);

    expect($subscription->value)->toBe(149.90);

    // Verify in Asaas
    $asaasSubscription = $subscription->asAsaasSubscription();
    expect((float) $asaasSubscription['value'])->toBe(149.90);

    // Cleanup
    $subscription->cancelNow();
    Asaas::customer()->delete($user->asaas_id);
});

test('user payments relationship includes all charges', function () {
    $user = User::create([
        'name' => 'Multiple Payments Test',
        'email' => 'multipay_'.uniqid().'@example.com',
        'cpf_cnpj' => generateTestCpf(),
    ]);

    $user->createAsAsaasCustomer();

    // Create multiple payments
    $payment1 = $user->chargeWithPix(25.00);
    $payment2 = $user->chargeWithPix(50.00);

    // Verify payments relationship
    expect($user->payments)->toHaveCount(2);

    // Cleanup
    Asaas::payment()->delete($payment1->asaas_id);
    Asaas::payment()->delete($payment2->asaas_id);
    Asaas::customer()->delete($user->asaas_id);
});
