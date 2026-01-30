<?php

declare(strict_types=1);

use Fevinta\CashierAsaas\Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "uses()" function to bind a different classes or traits.
|
*/

uses(TestCase::class)->in('Feature', 'Unit', 'Integration');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeActiveSubscription', function () {
    return $this->toBeInstanceOf(\Fevinta\CashierAsaas\Subscription::class)
        ->and($this->value->active())->toBeTrue();
});

expect()->extend('toBePaidPayment', function () {
    return $this->toBeInstanceOf(\Fevinta\CashierAsaas\Payment::class)
        ->and($this->value->isPaid())->toBeTrue();
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

/**
 * Skip test if no Asaas credentials are configured.
 */
function skipIfNoAsaasCredentials(): void
{
    if (empty(env('ASAAS_API_KEY'))) {
        test()->markTestSkipped('Asaas API key not configured. Set ASAAS_API_KEY to run integration tests.');
    }
}

/**
 * Create a unique CPF for testing.
 */
function generateTestCpf(): string
{
    $n = [];
    for ($i = 0; $i < 9; $i++) {
        $n[$i] = random_int(0, 9);
    }

    // First check digit
    $d1 = 0;
    for ($i = 0; $i < 9; $i++) {
        $d1 += $n[$i] * (10 - $i);
    }
    $d1 = 11 - ($d1 % 11);
    if ($d1 >= 10) {
        $d1 = 0;
    }
    $n[9] = $d1;

    // Second check digit
    $d2 = 0;
    for ($i = 0; $i < 10; $i++) {
        $d2 += $n[$i] * (11 - $i);
    }
    $d2 = 11 - ($d2 % 11);
    if ($d2 >= 10) {
        $d2 = 0;
    }
    $n[10] = $d2;

    return implode('', $n);
}

/**
 * Create a test webhook payload.
 */
function webhookPayload(string $event, array $data = []): array
{
    return array_merge([
        'event' => $event,
    ], $data);
}

/**
 * Create a payment webhook payload.
 */
function paymentWebhookPayload(string $event, array $paymentData = []): array
{
    return webhookPayload($event, [
        'payment' => array_merge([
            'id' => 'pay_'.uniqid(),
            'customer' => 'cus_'.uniqid(),
            'value' => 99.90,
            'netValue' => 97.90,
            'billingType' => 'PIX',
            'status' => 'PENDING',
            'dueDate' => now()->addDays(3)->format('Y-m-d'),
        ], $paymentData),
    ]);
}

/**
 * Create a subscription webhook payload.
 */
function subscriptionWebhookPayload(string $event, array $subscriptionData = []): array
{
    return webhookPayload($event, [
        'subscription' => array_merge([
            'id' => 'sub_'.uniqid(),
            'customer' => 'cus_'.uniqid(),
            'value' => 99.90,
            'cycle' => 'MONTHLY',
            'billingType' => 'PIX',
            'status' => 'ACTIVE',
            'nextDueDate' => now()->addMonth()->format('Y-m-d'),
        ], $subscriptionData),
    ]);
}

/**
 * Create an invoice webhook payload.
 */
function invoiceWebhookPayload(string $event, array $invoiceData = []): array
{
    return webhookPayload($event, [
        'invoice' => array_merge([
            'id' => 'inv_'.uniqid(),
            'status' => 'SCHEDULED',
            'value' => 100.00,
            'netValue' => 100.00,
            'serviceDescription' => 'Test Service',
            'effectiveDate' => now()->addDays(5)->format('Y-m-d'),
            'municipalServiceName' => 'Consultoria em TI',
        ], $invoiceData),
    ]);
}
