<?php

declare(strict_types=1);

use Fevinta\CashierAsaas\Events\BoletoGenerated;
use Fevinta\CashierAsaas\Events\PaymentConfirmed;
use Fevinta\CashierAsaas\Events\PaymentCreated;
use Fevinta\CashierAsaas\Events\PaymentDeleted;
use Fevinta\CashierAsaas\Events\PaymentOverdue;
use Fevinta\CashierAsaas\Events\PaymentReceived;
use Fevinta\CashierAsaas\Events\PaymentRefunded;
use Fevinta\CashierAsaas\Events\PaymentUpdated;
use Fevinta\CashierAsaas\Events\PixGenerated;
use Fevinta\CashierAsaas\Payment;
use Fevinta\CashierAsaas\Subscription;
use Fevinta\CashierAsaas\Tests\Fixtures\User;
use Illuminate\Support\Facades\Event;

beforeEach(function () {
    Event::fake();

    $this->user = User::create([
        'name' => 'Test User',
        'email' => 'test@example.com',
        'cpf_cnpj' => generateTestCpf(),
        'asaas_id' => 'cus_test123',
    ]);

    $this->subscription = Subscription::create([
        'user_id' => $this->user->id,
        'type' => 'default',
        'asaas_id' => 'sub_test123',
        'asaas_status' => 'ACTIVE',
        'plan' => 'premium',
        'value' => 99.90,
        'cycle' => 'MONTHLY',
        'billing_type' => 'PIX',
        'next_due_date' => now()->addMonth(),
    ]);
});

test('payment created webhook creates payment record', function () {
    $response = $this->postJson('/asaas/webhook', [
        'event' => 'PAYMENT_CREATED',
        'payment' => [
            'id' => 'pay_new123',
            'subscription' => 'sub_test123',
            'customer' => 'cus_test123',
            'billingType' => 'PIX',
            'value' => 99.90,
            'netValue' => 97.90,
            'status' => 'PENDING',
            'dueDate' => now()->addDays(3)->format('Y-m-d'),
        ],
    ]);

    $response->assertStatus(200);

    $payment = Payment::where('asaas_id', 'pay_new123')->first();
    expect($payment)->not->toBeNull();
    expect($payment->subscription_id)->toBe($this->subscription->id);
    expect((float) $payment->value)->toBe(99.90);
});

test('payment received webhook updates status', function () {
    $payment = Payment::create([
        'customer_id' => $this->user->id,
        'subscription_id' => $this->subscription->id,
        'asaas_id' => 'pay_test123',
        'billing_type' => 'PIX',
        'value' => 99.90,
        'net_value' => 97.90,
        'status' => 'PENDING',
        'due_date' => now()->addDays(3),
    ]);

    $response = $this->postJson('/asaas/webhook', [
        'event' => 'PAYMENT_RECEIVED',
        'payment' => [
            'id' => 'pay_test123',
            'status' => 'RECEIVED',
            'paymentDate' => now()->format('Y-m-d'),
            'netValue' => 97.90,
        ],
    ]);

    $response->assertStatus(200);

    $payment->refresh();
    expect($payment->status)->toBe('RECEIVED');
    expect($payment->payment_date)->not->toBeNull();
});

test('payment confirmed webhook updates status and date', function () {
    $payment = Payment::create([
        'customer_id' => $this->user->id,
        'subscription_id' => $this->subscription->id,
        'asaas_id' => 'pay_test123',
        'billing_type' => 'PIX',
        'value' => 99.90,
        'net_value' => 97.90,
        'status' => 'RECEIVED',
        'due_date' => now(),
        'payment_date' => now(),
    ]);

    $response = $this->postJson('/asaas/webhook', [
        'event' => 'PAYMENT_CONFIRMED',
        'payment' => [
            'id' => 'pay_test123',
            'status' => 'CONFIRMED',
        ],
    ]);

    $response->assertStatus(200);

    $payment->refresh();
    expect($payment->status)->toBe('CONFIRMED');
    expect($payment->confirmed_date)->not->toBeNull();
});

test('payment overdue webhook updates status', function () {
    $payment = Payment::create([
        'customer_id' => $this->user->id,
        'subscription_id' => $this->subscription->id,
        'asaas_id' => 'pay_test123',
        'billing_type' => 'BOLETO',
        'value' => 99.90,
        'net_value' => 97.90,
        'status' => 'PENDING',
        'due_date' => now()->subDays(1),
    ]);

    $response = $this->postJson('/asaas/webhook', [
        'event' => 'PAYMENT_OVERDUE',
        'payment' => [
            'id' => 'pay_test123',
            'status' => 'OVERDUE',
        ],
    ]);

    $response->assertStatus(200);

    $payment->refresh();
    expect($payment->status)->toBe('OVERDUE');
});

test('payment refunded webhook updates status', function () {
    $payment = Payment::create([
        'customer_id' => $this->user->id,
        'subscription_id' => $this->subscription->id,
        'asaas_id' => 'pay_test123',
        'billing_type' => 'PIX',
        'value' => 99.90,
        'net_value' => 97.90,
        'status' => 'CONFIRMED',
        'due_date' => now()->subDays(7),
        'payment_date' => now()->subDays(7),
    ]);

    $response = $this->postJson('/asaas/webhook', [
        'event' => 'PAYMENT_REFUNDED',
        'payment' => [
            'id' => 'pay_test123',
            'status' => 'REFUNDED',
        ],
    ]);

    $response->assertStatus(200);

    $payment->refresh();
    expect($payment->status)->toBe('REFUNDED');
    expect($payment->refunded_at)->not->toBeNull();
});

test('payment deleted webhook updates status', function () {
    $payment = Payment::create([
        'customer_id' => $this->user->id,
        'subscription_id' => $this->subscription->id,
        'asaas_id' => 'pay_test123',
        'billing_type' => 'BOLETO',
        'value' => 99.90,
        'net_value' => 97.90,
        'status' => 'PENDING',
        'due_date' => now()->addDays(3),
    ]);

    $response = $this->postJson('/asaas/webhook', [
        'event' => 'PAYMENT_DELETED',
        'payment' => [
            'id' => 'pay_test123',
        ],
    ]);

    $response->assertStatus(200);

    $payment->refresh();
    expect($payment->status)->toBe('DELETED');
});

test('payment updated webhook syncs data', function () {
    $payment = Payment::create([
        'customer_id' => $this->user->id,
        'subscription_id' => $this->subscription->id,
        'asaas_id' => 'pay_test123',
        'billing_type' => 'BOLETO',
        'value' => 99.90,
        'net_value' => 97.90,
        'status' => 'PENDING',
        'due_date' => now()->addDays(3),
    ]);

    $newDueDate = now()->addDays(10)->format('Y-m-d');

    $response = $this->postJson('/asaas/webhook', [
        'event' => 'PAYMENT_UPDATED',
        'payment' => [
            'id' => 'pay_test123',
            'value' => 149.90,
            'netValue' => 147.90,
            'status' => 'PENDING',
            'dueDate' => $newDueDate,
        ],
    ]);

    $response->assertStatus(200);

    $payment->refresh();
    expect((float) $payment->value)->toBe(149.90);
    expect((float) $payment->net_value)->toBe(147.90);
});

test('payment webhook dispatches PaymentCreated event', function () {
    $this->postJson('/asaas/webhook', [
        'event' => 'PAYMENT_CREATED',
        'payment' => [
            'id' => 'pay_new123',
            'subscription' => 'sub_test123',
            'billingType' => 'PIX',
            'value' => 99.90,
            'status' => 'PENDING',
            'dueDate' => now()->addDays(3)->format('Y-m-d'),
        ],
    ]);

    Event::assertDispatched(PaymentCreated::class, function ($event) {
        return $event->payment->asaas_id === 'pay_new123';
    });
});

test('payment webhook dispatches PaymentReceived event', function () {
    Payment::create([
        'customer_id' => $this->user->id,
        'asaas_id' => 'pay_test123',
        'billing_type' => 'PIX',
        'value' => 99.90,
        'net_value' => 97.90,
        'status' => 'PENDING',
        'due_date' => now(),
    ]);

    $this->postJson('/asaas/webhook', [
        'event' => 'PAYMENT_RECEIVED',
        'payment' => [
            'id' => 'pay_test123',
            'status' => 'RECEIVED',
        ],
    ]);

    Event::assertDispatched(PaymentReceived::class);
});

test('payment webhook dispatches PaymentConfirmed event', function () {
    Payment::create([
        'customer_id' => $this->user->id,
        'asaas_id' => 'pay_test123',
        'billing_type' => 'PIX',
        'value' => 99.90,
        'net_value' => 97.90,
        'status' => 'RECEIVED',
        'due_date' => now(),
    ]);

    $this->postJson('/asaas/webhook', [
        'event' => 'PAYMENT_CONFIRMED',
        'payment' => [
            'id' => 'pay_test123',
            'status' => 'CONFIRMED',
        ],
    ]);

    Event::assertDispatched(PaymentConfirmed::class);
});

test('payment webhook dispatches PaymentOverdue event', function () {
    Payment::create([
        'customer_id' => $this->user->id,
        'asaas_id' => 'pay_test123',
        'billing_type' => 'BOLETO',
        'value' => 99.90,
        'net_value' => 97.90,
        'status' => 'PENDING',
        'due_date' => now()->subDay(),
    ]);

    $this->postJson('/asaas/webhook', [
        'event' => 'PAYMENT_OVERDUE',
        'payment' => [
            'id' => 'pay_test123',
            'status' => 'OVERDUE',
        ],
    ]);

    Event::assertDispatched(PaymentOverdue::class);
});

test('payment webhook dispatches PaymentRefunded event', function () {
    Payment::create([
        'customer_id' => $this->user->id,
        'asaas_id' => 'pay_test123',
        'billing_type' => 'PIX',
        'value' => 99.90,
        'net_value' => 97.90,
        'status' => 'CONFIRMED',
        'due_date' => now(),
    ]);

    $this->postJson('/asaas/webhook', [
        'event' => 'PAYMENT_REFUNDED',
        'payment' => [
            'id' => 'pay_test123',
            'status' => 'REFUNDED',
        ],
    ]);

    Event::assertDispatched(PaymentRefunded::class);
});

test('payment webhook dispatches PaymentDeleted event', function () {
    Payment::create([
        'customer_id' => $this->user->id,
        'asaas_id' => 'pay_test123',
        'billing_type' => 'BOLETO',
        'value' => 99.90,
        'net_value' => 97.90,
        'status' => 'PENDING',
        'due_date' => now(),
    ]);

    $this->postJson('/asaas/webhook', [
        'event' => 'PAYMENT_DELETED',
        'payment' => [
            'id' => 'pay_test123',
        ],
    ]);

    Event::assertDispatched(PaymentDeleted::class);
});

test('payment webhook dispatches PaymentUpdated event', function () {
    Payment::create([
        'customer_id' => $this->user->id,
        'asaas_id' => 'pay_test123',
        'billing_type' => 'BOLETO',
        'value' => 99.90,
        'net_value' => 97.90,
        'status' => 'PENDING',
        'due_date' => now(),
    ]);

    $this->postJson('/asaas/webhook', [
        'event' => 'PAYMENT_UPDATED',
        'payment' => [
            'id' => 'pay_test123',
            'value' => 149.90,
            'status' => 'PENDING',
            'dueDate' => now()->addDays(5)->format('Y-m-d'),
        ],
    ]);

    Event::assertDispatched(PaymentUpdated::class);
});

test('pix payment created dispatches PixGenerated event', function () {
    $this->postJson('/asaas/webhook', [
        'event' => 'PAYMENT_CREATED',
        'payment' => [
            'id' => 'pay_pix123',
            'subscription' => 'sub_test123',
            'billingType' => 'PIX',
            'value' => 99.90,
            'status' => 'PENDING',
            'dueDate' => now()->addDays(3)->format('Y-m-d'),
            'pixQrCode' => 'data:image/png;base64,abc123',
            'pixCopiaECola' => '00020126580014br.gov.bcb.pix',
        ],
    ]);

    Event::assertDispatched(PixGenerated::class, function ($event) {
        return $event->qrCode() === 'data:image/png;base64,abc123'
            && $event->copyPaste() === '00020126580014br.gov.bcb.pix';
    });
});

test('boleto payment created dispatches BoletoGenerated event', function () {
    $this->postJson('/asaas/webhook', [
        'event' => 'PAYMENT_CREATED',
        'payment' => [
            'id' => 'pay_boleto123',
            'subscription' => 'sub_test123',
            'billingType' => 'BOLETO',
            'value' => 99.90,
            'status' => 'PENDING',
            'dueDate' => now()->addDays(3)->format('Y-m-d'),
            'bankSlipUrl' => 'https://sandbox.asaas.com/b/123',
            'identificationField' => '23793.38128 60800.000003',
        ],
    ]);

    Event::assertDispatched(BoletoGenerated::class, function ($event) {
        return $event->bankSlipUrl() === 'https://sandbox.asaas.com/b/123';
    });
});

test('payment created with empty id returns 200 but does not create payment', function () {
    $response = $this->postJson('/asaas/webhook', [
        'event' => 'PAYMENT_CREATED',
        'payment' => [
            'id' => '',
            'billingType' => 'PIX',
            'value' => 99.90,
            'status' => 'PENDING',
        ],
    ]);

    $response->assertStatus(200);
    Event::assertNotDispatched(PaymentCreated::class);
});

test('payment created with externalReference finds customer without subscription', function () {
    // Create payment with externalReference pointing to user but no subscription
    $response = $this->postJson('/asaas/webhook', [
        'event' => 'PAYMENT_CREATED',
        'payment' => [
            'id' => 'pay_ext_ref123',
            'customer' => 'cus_test123',
            'billingType' => 'PIX',
            'value' => 50.00,
            'status' => 'PENDING',
            'dueDate' => now()->addDays(3)->format('Y-m-d'),
            'externalReference' => (string) $this->user->id,
        ],
    ]);

    $response->assertStatus(200);

    $payment = Payment::where('asaas_id', 'pay_ext_ref123')->first();
    expect($payment)->not->toBeNull();
    expect($payment->customer_id)->toBe($this->user->id);
    expect($payment->subscription_id)->toBeNull();
});

test('payment bank slip viewed webhook dispatches BoletoRegistered event', function () {
    $payment = Payment::create([
        'customer_id' => $this->user->id,
        'subscription_id' => $this->subscription->id,
        'asaas_id' => 'pay_boleto_viewed123',
        'billing_type' => 'BOLETO',
        'value' => 99.90,
        'net_value' => 97.90,
        'status' => 'PENDING',
        'due_date' => now()->addDays(3),
        'bank_slip_url' => 'https://sandbox.asaas.com/b/123',
    ]);

    $response = $this->postJson('/asaas/webhook', [
        'event' => 'PAYMENT_BANK_SLIP_VIEWED',
        'payment' => [
            'id' => 'pay_boleto_viewed123',
            'billingType' => 'BOLETO',
        ],
    ]);

    $response->assertStatus(200);
    Event::assertDispatched(\Fevinta\CashierAsaas\Events\BoletoRegistered::class, function ($event) use ($payment) {
        return $event->payment->id === $payment->id;
    });
});

test('payment bank slip viewed does not dispatch event for non-boleto payment', function () {
    $payment = Payment::create([
        'customer_id' => $this->user->id,
        'subscription_id' => $this->subscription->id,
        'asaas_id' => 'pay_pix_viewed123',
        'billing_type' => 'PIX',
        'value' => 99.90,
        'net_value' => 97.90,
        'status' => 'PENDING',
        'due_date' => now()->addDays(3),
    ]);

    $response = $this->postJson('/asaas/webhook', [
        'event' => 'PAYMENT_BANK_SLIP_VIEWED',
        'payment' => [
            'id' => 'pay_pix_viewed123',
        ],
    ]);

    $response->assertStatus(200);
    Event::assertNotDispatched(\Fevinta\CashierAsaas\Events\BoletoRegistered::class);
});

test('payment bank slip viewed does nothing when payment not found', function () {
    $response = $this->postJson('/asaas/webhook', [
        'event' => 'PAYMENT_BANK_SLIP_VIEWED',
        'payment' => [
            'id' => 'pay_unknown',
        ],
    ]);

    $response->assertStatus(200);
    Event::assertNotDispatched(\Fevinta\CashierAsaas\Events\BoletoRegistered::class);
});

test('payment received does nothing when payment not found', function () {
    $response = $this->postJson('/asaas/webhook', [
        'event' => 'PAYMENT_RECEIVED',
        'payment' => [
            'id' => 'pay_nonexistent',
            'status' => 'RECEIVED',
        ],
    ]);

    $response->assertStatus(200);
    Event::assertNotDispatched(PaymentReceived::class);
});

test('payment received does nothing when payment id is null', function () {
    $response = $this->postJson('/asaas/webhook', [
        'event' => 'PAYMENT_RECEIVED',
        'payment' => [
            'status' => 'RECEIVED',
        ],
    ]);

    $response->assertStatus(200);
    Event::assertNotDispatched(PaymentReceived::class);
});
