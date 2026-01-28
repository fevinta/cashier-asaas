# Laravel Cashier-style Asaas (Unofficial)

[![Latest Version on Packagist](https://img.shields.io/packagist/v/fevinta/cashier-asaas.svg)](https://packagist.org/packages/fevinta/cashier-asaas)
[![Tests](https://github.com/fevinta/cashier-asaas/actions/workflows/tests.yml/badge.svg)](https://github.com/fevinta/cashier-asaas/actions)
[![codecov](https://codecov.io/gh/fevinta/cashier-asaas/branch/main/graph/badge.svg)](https://codecov.io/gh/fevinta/cashier-asaas)
[![Total Downloads](https://img.shields.io/packagist/dt/fevinta/cashier-asaas.svg)](https://packagist.org/packages/fevinta/cashier-asaas)
[![License](https://img.shields.io/packagist/l/fevinta/cashier-asaas.svg)](LICENSE)

Laravel Cashier-style subscription billing for [Asaas](https://www.asaas.com) payment gateway (Brazil).

## Features

- 🇧🇷 **Brazilian Payment Methods**: PIX, Boleto, Credit Card
- 💳 **Subscription Management**: Create, update, cancel, resume subscriptions
- 🔄 **Plan Swapping**: Change plans with automatic proration
- ⏰ **Trial Periods**: Support for trial days
- 🪝 **Webhook Handling**: Automatic payment status updates
- 🎯 **Laravel-like API**: Familiar Cashier-style fluent interface
- 🛒 **Asaas Checkout**: Hosted checkout page (like Stripe Checkout)
- 🧾 **Invoice (NFS-e)**: Issue and manage Notas Fiscais de Serviço

## Installation

```bash
composer require fevinta/cashier-asaas
```

Publish the configuration and migrations:

```bash
php artisan vendor:publish --tag=cashier-asaas-config
php artisan vendor:publish --tag=cashier-asaas-migrations
php artisan migrate
```

## Configuration

Add your Asaas credentials to `.env`:

```env
ASAAS_API_KEY=your-api-key
ASAAS_SANDBOX=true
ASAAS_WEBHOOK_TOKEN=optional-webhook-token
```

Define your subscription plans in `config/cashier-asaas.php`:

```php
'plans' => [
    'basic' => [
        'price' => 29.90,
        'name' => 'Plano Básico',
    ],
    'pro' => [
        'price' => 99.90,
        'name' => 'Plano Pro',
    ],
    'enterprise' => [
        'price' => 299.90,
        'name' => 'Plano Enterprise',
    ],
],
```

## Setup

Add the `Billable` trait to your User model:

```php
use Fevinta\CashierAsaas\Billable;

class User extends Authenticatable
{
    use Billable;
    
    // Optional: customize customer data for Asaas
    public function asaasCpfCnpj(): ?string
    {
        return $this->document;
    }
}
```

## Usage

### Creating Subscriptions

```php
// Basic subscription with credit card
$user->newSubscription('default', 'pro')
    ->withCreditCard([
        'holderName' => 'John Doe',
        'number' => '4111111111111111',
        'expiryMonth' => '12',
        'expiryYear' => '2025',
        'ccv' => '123',
    ], [
        'name' => 'John Doe',
        'email' => 'john@example.com',
        'cpfCnpj' => '12345678901',
        'postalCode' => '01310100',
        'addressNumber' => '123',
    ])
    ->create();

// Subscription with boleto
$user->newSubscription('default', 'basic')
    ->withBoleto()
    ->create();

// Subscription with PIX
$user->newSubscription('default', 'basic')
    ->withPix()
    ->create();

// Let customer choose payment method
$user->newSubscription('default', 'pro')
    ->askCustomer()
    ->create();

// With trial period
$user->newSubscription('default', 'pro')
    ->trialDays(14)
    ->withCreditCardToken($token)
    ->create();

// Yearly subscription
$user->newSubscription('default', 'pro')
    ->yearly()
    ->withCreditCardToken($token)
    ->create();

// Custom price (override plan config)
$user->newSubscription('default', 'custom')
    ->price(149.90)
    ->monthly()
    ->withBoleto()
    ->create();
```

### Checking Subscription Status

```php
// Check if subscribed
if ($user->subscribed('default')) {
    // Has active subscription
}

// Check specific plan
if ($user->subscribedToPlan('pro', 'default')) {
    // Subscribed to Pro plan
}

// Check trial
if ($user->onTrial('default')) {
    // On trial period
}

// Get subscription
$subscription = $user->subscription('default');

// Check subscription state
$subscription->active();     // Is active
$subscription->onTrial();    // On trial
$subscription->cancelled();  // Has been cancelled
$subscription->onGracePeriod(); // Cancelled but still active
$subscription->ended();      // Completely ended
```

### Managing Subscriptions

```php
$subscription = $user->subscription('default');

// Cancel (at period end)
$subscription->cancel();

// Cancel immediately
$subscription->cancelNow();

// Resume cancelled subscription (if on grace period)
$subscription->resume();

// Swap to different plan
$subscription->swap('enterprise');

// Update price
$subscription->updateValue(149.90);

// Change billing type
$subscription->changeBillingType(BillingType::PIX);

// Update credit card
$subscription->updateCreditCard($cardData, $holderInfo);

// Or with token
$subscription->updateCreditCardToken($newToken);
```

### Plan Swapping & Proration

When you swap plans, automatic proration is applied via the Asaas API. Here's how it works:

**Example: Upgrading from R$10/month to R$20/month**

```
Starting point:
- Current plan: R$10/month
- Billing cycle: 30 days
- Days already used: 15 days
- Days remaining: 15 days

When you upgrade to R$20/month:

1. Credit for unused time at old rate:
   R$10 x (15/30) = R$5.00

2. Charge for remaining time at new rate:
   R$20 x (15/30) = R$10.00

3. Prorated upgrade charge:
   R$10.00 - R$5.00 = R$5.00 (added to next payment)

4. Next full payment: R$20.00
```

The `swap()` method sends `updatePendingPayments: true` to Asaas, which automatically:
- Calculates the prorated difference based on days remaining
- Adjusts pending invoices to include the prorated amount
- Sets all future payments to the new price

```php
// Swap using config price
$subscription->swap('premium');

// Swap with custom price
$subscription->swap('custom', 99.90);

// Just update price without changing plan name
$subscription->updateValue(99.90);
```

### Single Charges

```php
use Fevinta\CashierAsaas\Enums\BillingType;

// Charge with PIX
$payment = $user->charge(100.00, BillingType::PIX, [
    'description' => 'Product purchase',
    'dueDate' => now()->addDays(3),
]);

// Charge with boleto
$payment = $user->charge(100.00, BillingType::BOLETO, [
    'description' => 'Service fee',
    'dueDate' => now()->addDays(5),
]);

// Charge with credit card
$payment = $user->charge(100.00, BillingType::CREDIT_CARD, [
    'description' => 'Premium feature',
    'creditCardToken' => $token,
]);

// Installment payment (credit card only)
$payment = $user->chargeInstallments(600.00, 6, [
    'description' => 'Annual plan',
    'creditCardToken' => $token,
]);
```

### Webhooks

The package automatically handles Asaas webhooks. Configure the webhook URL in your Asaas dashboard:

```
https://your-app.com/asaas/webhook
```

Available events you can listen to:

```php
// In EventServiceProvider
protected $listen = [
    \Fevinta\CashierAsaas\Events\PaymentReceived::class => [
        \App\Listeners\HandlePaymentReceived::class,
    ],
    \Fevinta\CashierAsaas\Events\PaymentOverdue::class => [
        \App\Listeners\HandlePaymentOverdue::class,
    ],
    \Fevinta\CashierAsaas\Events\PaymentRefunded::class => [
        \App\Listeners\HandlePaymentRefunded::class,
    ],
];
```

### Middleware

Protect routes requiring subscription:

```php
Route::middleware(['auth', 'subscribed'])->group(function () {
    Route::get('/premium', PremiumController::class);
});
```

Register the middleware in your Kernel:

```php
protected $middlewareAliases = [
    'subscribed' => \Fevinta\CashierAsaas\Http\Middleware\EnsureUserIsSubscribed::class,
];
```

### Payment Split

Share revenue with partners:

```php
$user->newSubscription('default', 'pro')
    ->split('wallet_partner_id', fixedValue: 10.00)  // R$ 10 fixed
    ->split('wallet_affiliate_id', percentualValue: 10)  // 10%
    ->withCreditCardToken($token)
    ->create();
```

### Asaas Checkout

The package provides a powerful checkout session builder that redirects customers to Asaas's hosted checkout page. This is similar to Stripe Checkout and allows customers to complete payments without you handling sensitive payment data.

#### Basic Usage

```php
use Fevinta\CashierAsaas\Checkout;

// Quick checkout for existing customer
$checkout = $user->checkoutCharge(99.90, 'Premium Feature');

// Redirect to checkout page
return $checkout->redirect();

// Or get the URL
$url = $checkout->url();
```

#### Guest Checkout (No Account Required)

```php
use Fevinta\CashierAsaas\Checkout;

// Guest checkout - customer data collected on checkout page
$checkout = Checkout::guest()
    ->charge(199.90, 'Product Purchase')
    ->allowAllPaymentMethods()
    ->successUrl('https://your-app.com/success')
    ->create();

return $checkout->redirect();

// Or pre-fill customer data
$checkout = Checkout::guest()
    ->charge(199.90, 'Product Purchase')
    ->customerName('John Doe')
    ->customerEmail('john@example.com')
    ->customerCpfCnpj('12345678901')
    ->create();
```

#### Customer Checkout (Existing User)

```php
// Using the Billable trait
$checkout = $user->newCheckout()
    ->charge(99.90, 'Premium Feature')
    ->onlyPix()
    ->successUrl('https://your-app.com/success')
    ->create();

// Multiple items
$checkout = $user->checkout([
    ['name' => 'Product A', 'value' => 50.00, 'quantity' => 2],
    ['name' => 'Product B', 'value' => 30.00, 'quantity' => 1],
]);
```

#### Payment Method Options

```php
// Allow all payment methods (PIX, Boleto, Credit Card)
$checkout = $user->newCheckout()
    ->charge(100.00, 'Order #123')
    ->allowAllPaymentMethods()
    ->create();

// Only specific methods
$checkout = $user->newCheckout()
    ->charge(100.00, 'Order #123')
    ->onlyPix()
    ->create();

$checkout = $user->newCheckout()
    ->charge(100.00, 'Order #123')
    ->onlyBoleto()
    ->create();

$checkout = $user->newCheckout()
    ->charge(100.00, 'Order #123')
    ->onlyCreditCard()
    ->create();

// Combine methods
$checkout = $user->newCheckout()
    ->charge(100.00, 'Order #123')
    ->withPix()
    ->withCreditCard()
    ->create();
```

#### Installment Payments

```php
// Fixed installments (credit card only)
$checkout = $user->newCheckout()
    ->charge(600.00, 'Annual Plan')
    ->installments(6) // 6x R$100.00
    ->create();

// Let customer choose installments (up to max)
$checkout = $user->newCheckout()
    ->charge(1200.00, 'Premium Package')
    ->maxInstallments(12) // Customer chooses 1-12x
    ->create();
```

#### Recurring/Subscription Checkout

```php
use Fevinta\CashierAsaas\Enums\SubscriptionCycle;

// Monthly subscription via checkout
$checkout = $user->newCheckout()
    ->charge(99.90, 'Pro Plan')
    ->monthly()
    ->create();

// Yearly subscription
$checkout = $user->newCheckout()
    ->charge(999.00, 'Pro Plan - Annual')
    ->yearly()
    ->create();

// Other cycles
$checkout = $user->newCheckout()
    ->charge(49.90, 'Basic Plan')
    ->weekly()
    ->create();

$checkout = $user->newCheckout()
    ->charge(79.90, 'Standard Plan')
    ->quarterly()
    ->create();
```

#### Redirect URLs

```php
$checkout = $user->newCheckout()
    ->charge(100.00, 'Order #123')
    ->successUrl('https://your-app.com/checkout/success')
    ->cancelUrl('https://your-app.com/checkout/canceled')
    ->expiredUrl('https://your-app.com/checkout/expired')
    ->create();
```

Or configure defaults in `config/cashier-asaas.php`:

```php
'checkout' => [
    'success_url' => env('ASAAS_CHECKOUT_SUCCESS_URL'),
    'cancel_url' => env('ASAAS_CHECKOUT_CANCEL_URL'),
    'expired_url' => env('ASAAS_CHECKOUT_EXPIRED_URL'),
    'expiration_minutes' => env('ASAAS_CHECKOUT_EXPIRATION', 60),
],
```

#### Session Options

```php
$checkout = $user->newCheckout()
    ->charge(100.00, 'Order #123')
    ->expiresIn(60) // Expires in 60 minutes
    ->dueDateLimitDays(5) // Boleto due date limit
    ->externalReference('order-123')
    ->description('Purchase from My Store')
    ->withMetadata(['order_id' => 123])
    ->create();
```

#### Payment Split in Checkout

```php
$checkout = $user->newCheckout()
    ->charge(100.00, 'Marketplace Order')
    ->split('wallet_seller_id', fixedValue: 80.00)
    ->split('wallet_platform_id', percentualValue: 20)
    ->create();
```

#### Using the Checkout Response

```php
$checkout = $user->newCheckout()
    ->charge(100.00, 'Order')
    ->create();

// Get checkout data
$id = $checkout->id();
$url = $checkout->url();
$status = $checkout->status();
$session = $checkout->session(); // Full API response

// Redirect (in controller)
return $checkout->redirect();

// Or return as response (implements Responsable)
return $checkout; // Auto-redirects

// JSON response
return response()->json($checkout->toArray());
```

#### Checkout Webhook Events

```php
// In EventServiceProvider
protected $listen = [
    \Fevinta\CashierAsaas\Events\CheckoutCreated::class => [
        \App\Listeners\HandleCheckoutCreated::class,
    ],
    \Fevinta\CashierAsaas\Events\CheckoutPaid::class => [
        \App\Listeners\HandleCheckoutPaid::class,
    ],
    \Fevinta\CashierAsaas\Events\CheckoutCanceled::class => [
        \App\Listeners\HandleCheckoutCanceled::class,
    ],
    \Fevinta\CashierAsaas\Events\CheckoutExpired::class => [
        \App\Listeners\HandleCheckoutExpired::class,
    ],
];
```

### Invoices (Nota Fiscal de Serviço)

The package supports issuing **NFS-e (Nota Fiscal de Serviço)** through the Asaas API. Invoices are scheduled via the API, processed with the city hall (prefeitura), and kept in sync locally through webhooks.

#### Configuration

Add the following to your project's `.env`:

```env
# Enable invoice support
ASAAS_INVOICE_ENABLED=true

# Default service description and observations
ASAAS_INVOICE_SERVICE_DESCRIPTION="Your service description"
ASAAS_INVOICE_OBSERVATIONS=

# When the invoice effective date is set for subscription invoices:
# ON_PAYMENT_CONFIRMATION | ON_PAYMENT_DUE_DATE | BEFORE_PAYMENT_DUE_DATE | ON_DUE_DATE_MONTH | ON_NEXT_MONTH
ASAAS_INVOICE_EFFECTIVE_DATE_PERIOD=ON_PAYMENT_CONFIRMATION

# Days before due date (only used with BEFORE_PAYMENT_DUE_DATE). Valid: 5, 10, 15, 30, 60
ASAAS_INVOICE_DAYS_BEFORE=5

# Default tax rates (all optional, default 0)
ASAAS_INVOICE_RETAIN_ISS=false
ASAAS_INVOICE_ISS=0
ASAAS_INVOICE_COFINS=0
ASAAS_INVOICE_CSLL=0
ASAAS_INVOICE_INSS=0
ASAAS_INVOICE_IR=0
ASAAS_INVOICE_PIS=0

# Municipal service defaults
ASAAS_INVOICE_MUNICIPAL_SERVICE_ID=
ASAAS_INVOICE_MUNICIPAL_SERVICE_CODE=
ASAAS_INVOICE_MUNICIPAL_SERVICE_NAME=
```

Run the migration to create the `asaas_invoices` table:

```bash
php artisan migrate
```

#### Scheduling an Invoice

```php
use Fevinta\CashierAsaas\Asaas;

$result = Asaas::invoice()->schedule([
    'customer'             => $asaasCustomerId,
    'serviceDescription'   => 'Software Development',
    'value'                => 5000.00,
    'effectiveDate'        => '2026-01-28',
    'municipalServiceName' => 'Desenvolvimento de software',
    'deductions'           => 500.00,  // optional
    'taxes'                => [        // optional, overrides .env defaults
        'retainIss' => true,
        'iss'       => 5.0,
    ],
]);
```

#### Working with the Invoice Model

```php
use Fevinta\CashierAsaas\Invoice;

$invoice = Invoice::find($id);

// Issue the NFS-e immediately
$invoice->authorize();

// Request cancellation
$invoice->cancel();

// Refresh local data from the Asaas API
$invoice->syncFromAsaas();

// Check status
$invoice->isScheduled();
$invoice->isSynchronized();
$invoice->isAuthorized();
$invoice->isCanceled();
$invoice->hasError();

// Get document URLs
$invoice->pdfUrl();
$invoice->xmlUrl();

// Query scopes
Invoice::authorized()->get();
Invoice::scheduled()->where('customer_id', $customerId)->get();
```

#### Status Lifecycle

```
SCHEDULED → SYNCHRONIZED → AUTHORIZED
                         → ERROR

AUTHORIZED → PROCESSING_CANCELLATION → CANCELED
                                     → CANCELLATION_DENIED
```

#### API Queries

```php
Asaas::invoice()->findByPayment($paymentId);
Asaas::invoice()->findByCustomer($customerId);
Asaas::invoice()->findByDateRange('2026-01-01', '2026-01-31');
Asaas::invoice()->findByStatus('AUTHORIZED');

// Fiscal and municipal service info
Asaas::invoice()->fiscalInfo();
Asaas::invoice()->saveFiscalInfo([...]);
Asaas::invoice()->municipalServices();
```

#### Subscription Invoice Auto-Generation

Configure automatic NFS-e issuance for subscription payments:

```php
Asaas::invoice()->configureSubscriptionInvoice($subscriptionId, [
    'effectiveDatePeriod' => 'ON_PAYMENT_CONFIRMATION',
    'serviceDescription'  => 'Monthly SaaS Service',
]);

Asaas::invoice()->getSubscriptionInvoiceSettings($subscriptionId);
Asaas::invoice()->deleteSubscriptionInvoiceSettings($subscriptionId);
```

#### Invoice Webhook Events

The webhook controller automatically syncs invoice data to the local database and dispatches events:

| Webhook Event | Event Class | Invoice Status |
|---|---|---|
| `INVOICE_CREATED` | `InvoiceCreated` | `SCHEDULED` |
| `INVOICE_UPDATED` | `InvoiceUpdated` | *(varies)* |
| `INVOICE_SYNCHRONIZED` | `InvoiceSynchronized` | `SYNCHRONIZED` |
| `INVOICE_AUTHORIZED` | `InvoiceAuthorized` | `AUTHORIZED` |
| `INVOICE_CANCELED` | `InvoiceCanceled` | `CANCELED` |
| `INVOICE_CANCELLATION_DENIED` | `InvoiceCancellationDenied` | `CANCELLATION_DENIED` |
| `INVOICE_ERROR` | `InvoiceError` | `ERROR` |

Listen to invoice events in your application:

```php
use Fevinta\CashierAsaas\Events\InvoiceAuthorized;
use Fevinta\CashierAsaas\Events\InvoiceError;

// In EventServiceProvider
protected $listen = [
    InvoiceAuthorized::class => [
        \App\Listeners\SendInvoiceNotification::class,
    ],
    InvoiceError::class => [
        \App\Listeners\HandleInvoiceError::class,
    ],
];
```

#### Custom Invoice Model

If you need to extend the default Invoice model:

```php
use Fevinta\CashierAsaas\Cashier;

Cashier::useInvoiceModel(YourCustomInvoice::class);
```

## API Reference

### Billable Trait Methods

| Method | Description |
|--------|-------------|
| `createAsAsaasCustomer()` | Create customer in Asaas |
| `updateAsaasCustomer()` | Update customer data |
| `asAsaasCustomer()` | Get Asaas customer data |
| `newSubscription($type, $plan)` | Start subscription builder |
| `subscription($type)` | Get subscription by type |
| `subscribed($type)` | Check if subscribed |
| `onTrial($type)` | Check if on trial |
| `charge($amount, $type, $options)` | Single charge |
| `newCheckout()` | Start checkout builder |
| `checkout($items, $options)` | Create checkout with items |
| `checkoutCharge($amount, $name)` | Quick single charge checkout |

### Subscription Methods

| Method | Description |
|--------|-------------|
| `active()` | Is subscription active |
| `valid()` | Is subscription valid (active/trial/grace) |
| `cancel()` | Cancel at period end |
| `cancelNow()` | Cancel immediately |
| `resume()` | Resume cancelled subscription |
| `swap($plan)` | Change plan |
| `updateValue($value)` | Update subscription price |
| `updateCreditCard()` | Update payment card |

### Checkout Builder Methods

| Method | Description |
|--------|-------------|
| `charge($amount, $description)` | Add single item |
| `addItem($name, $value, $qty)` | Add item to checkout |
| `items($items)` | Set multiple items |
| `allowAllPaymentMethods()` | Enable PIX, Boleto, Credit Card |
| `onlyPix()` | Only allow PIX |
| `onlyBoleto()` | Only allow Boleto |
| `onlyCreditCard()` | Only allow Credit Card |
| `withPix()` / `withBoleto()` / `withCreditCard()` | Add payment method |
| `oneTime()` | One-time payment (default) |
| `installments($count)` | Fixed installment payment |
| `maxInstallments($count)` | Customer chooses installments |
| `monthly()` / `yearly()` / `weekly()` | Recurring payment cycles |
| `successUrl($url)` | Set success redirect |
| `cancelUrl($url)` | Set cancel redirect |
| `expiredUrl($url)` | Set expired redirect |
| `expiresIn($minutes)` | Set session expiration |
| `externalReference($ref)` | Set external reference |
| `split($walletId, ...)` | Add payment split |
| `create()` | Create checkout session |

### Checkout Response Methods

| Method | Description |
|--------|-------------|
| `id()` | Get checkout session ID |
| `url()` | Get checkout page URL |
| `status()` | Get checkout status |
| `session()` | Get full API response |
| `redirect()` | Redirect to checkout page |
| `toArray()` | Convert to array |
| `toJson()` | Convert to JSON |

## Testing

The package uses PEST for testing with a dual approach: mocked HTTP for fast unit/feature tests, and real Asaas Sandbox API for integration tests.

### Run All Tests (Mocked)

```bash
# Using composer script
composer test

# Or directly with PEST
./vendor/bin/pest

# With coverage report
./vendor/bin/pest --coverage
```

### Run Specific Test Suites

```bash
# Unit tests only
./vendor/bin/pest --testsuite=Unit

# Feature tests only
./vendor/bin/pest --testsuite=Feature

# Integration tests (requires Asaas credentials)
./vendor/bin/pest --testsuite=Integration
```

### Integration Tests (Real Asaas Sandbox)

Integration tests hit the real Asaas Sandbox API. They are skipped by default when no credentials are configured.

```bash
# Set your Asaas Sandbox API key
export ASAAS_API_KEY=your_sandbox_api_key_here

# Run integration tests
./vendor/bin/pest --testsuite=Integration
```

### Static Analysis

```bash
# Run PHPStan
./vendor/bin/phpstan analyse
```

### Test Configuration

Environment variables for testing:

| Variable | Description | Default |
|----------|-------------|---------|
| `ASAAS_API_KEY` | Asaas API key (required for integration tests) | - |
| `ASAAS_SANDBOX` | Enable sandbox mode | `true` |
| `ASAAS_WEBHOOK_TOKEN` | Webhook verification token | - |

## License

MIT License. See [LICENSE](LICENSE) for details.
