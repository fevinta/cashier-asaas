# Laravel Cashier-style Asaas (Unofficial)

Laravel Cashier-style subscription billing for [Asaas](https://www.asaas.com) payment gateway (Brazil).

## Features

- 🇧🇷 **Brazilian Payment Methods**: PIX, Boleto, Credit Card
- 💳 **Subscription Management**: Create, update, cancel, resume subscriptions
- 🔄 **Plan Swapping**: Change plans with automatic proration
- ⏰ **Trial Periods**: Support for trial days
- 🪝 **Webhook Handling**: Automatic payment status updates
- 🎯 **Laravel-like API**: Familiar Cashier-style fluent interface

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
export ASAAS_API_KEY=your_sandbox_api_key

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
