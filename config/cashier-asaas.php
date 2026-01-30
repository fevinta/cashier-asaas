<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Asaas API Key
    |--------------------------------------------------------------------------
    |
    | The API key used to authenticate with Asaas. You can obtain this key
    | from your Asaas dashboard under Profile > Integrations.
    |
    */

    'api_key' => env('ASAAS_API_KEY'),

    /*
    |--------------------------------------------------------------------------
    | Asaas Sandbox Mode
    |--------------------------------------------------------------------------
    |
    | When enabled, all API calls will be made to the Asaas sandbox
    | environment. This is useful for testing without real transactions.
    |
    */

    'sandbox' => env('ASAAS_SANDBOX', true),

    /*
    |--------------------------------------------------------------------------
    | Billable Model
    |--------------------------------------------------------------------------
    |
    | This is the model that will be used for billing. By default, it uses
    | the User model, but you can change it to any Eloquent model.
    |
    */

    'model' => env('CASHIER_MODEL', App\Models\User::class),

    /*
    |--------------------------------------------------------------------------
    | Currency
    |--------------------------------------------------------------------------
    |
    | The default currency for all transactions. Asaas operates in BRL.
    |
    */

    'currency' => 'BRL',

    /*
    |--------------------------------------------------------------------------
    | Currency Locale
    |--------------------------------------------------------------------------
    |
    | The locale used for formatting currency values.
    |
    */

    'currency_locale' => 'pt_BR',

    /*
    |--------------------------------------------------------------------------
    | Webhook Path
    |--------------------------------------------------------------------------
    |
    | The path where Asaas webhook events will be received.
    |
    */

    'webhook_path' => env('ASAAS_WEBHOOK_PATH', 'asaas/webhook'),

    /*
    |--------------------------------------------------------------------------
    | Webhook Token
    |--------------------------------------------------------------------------
    |
    | Optional webhook authentication token. If set, the webhook controller
    | will validate incoming requests against this token.
    |
    */

    'webhook_token' => env('ASAAS_WEBHOOK_TOKEN'),

    /*
    |--------------------------------------------------------------------------
    | Subscription Plans
    |--------------------------------------------------------------------------
    |
    | Define your subscription plans here with their prices. This allows
    | you to reference plans by name instead of hardcoding prices.
    |
    | Example:
    | 'plans' => [
    |     'basic' => [
    |         'price' => 29.90,
    |         'name' => 'Plano Básico',
    |     ],
    |     'pro' => [
    |         'price' => 99.90,
    |         'name' => 'Plano Pro',
    |     ],
    | ],
    |
    */

    'plans' => [
        // Add your plans here
    ],

    /*
    |--------------------------------------------------------------------------
    | Default Billing Type
    |--------------------------------------------------------------------------
    |
    | The default payment method for subscriptions. Options:
    | BOLETO, CREDIT_CARD, PIX, UNDEFINED (let customer choose)
    |
    */

    'default_billing_type' => env('ASAAS_DEFAULT_BILLING_TYPE', 'UNDEFINED'),

    /*
    |--------------------------------------------------------------------------
    | Default Subscription Cycle
    |--------------------------------------------------------------------------
    |
    | The default billing cycle for subscriptions. Options:
    | WEEKLY, BIWEEKLY, MONTHLY, QUARTERLY, SEMIANNUALLY, YEARLY
    |
    */

    'default_cycle' => env('ASAAS_DEFAULT_CYCLE', 'MONTHLY'),

    /*
    |--------------------------------------------------------------------------
    | Notification Settings
    |--------------------------------------------------------------------------
    |
    | Configure whether Asaas should send notifications to customers.
    |
    */

    'notifications' => [
        'enabled' => env('ASAAS_NOTIFICATIONS_ENABLED', true),
        'email' => env('ASAAS_NOTIFICATIONS_EMAIL', true),
        'sms' => env('ASAAS_NOTIFICATIONS_SMS', false),
        'phone_call' => env('ASAAS_NOTIFICATIONS_PHONE', false),
    ],

    /*
    |--------------------------------------------------------------------------
    | Fine and Interest
    |--------------------------------------------------------------------------
    |
    | Default fine and interest values for late payments.
    |
    */

    'late_payment' => [
        'fine_percentage' => env('ASAAS_FINE_PERCENTAGE', 0),
        'interest_percentage' => env('ASAAS_INTEREST_PERCENTAGE', 0),
    ],

    /*
    |--------------------------------------------------------------------------
    | Nota Fiscal (Invoice) Settings
    |--------------------------------------------------------------------------
    |
    | Configure invoice (Nota Fiscal) generation settings.
    |
    | Effective date periods for subscriptions:
    | - ON_PAYMENT_CONFIRMATION: Issue only when payment is confirmed
    | - ON_PAYMENT_DUE_DATE: On the due date
    | - BEFORE_PAYMENT_DUE_DATE: X days before due date (use days_before_due_date)
    | - ON_DUE_DATE_MONTH: 1st day of the due date month
    | - ON_NEXT_MONTH: 1st day of the month following the due date
    |
    */

    'invoice' => [
        // Enable or disable invoice (Nota Fiscal) generation
        'enabled' => env('ASAAS_INVOICE_ENABLED', false),

        // Days before due date (used with BEFORE_PAYMENT_DUE_DATE period)
        // Valid values: 5, 10, 15, 30, 60
        'days_before_due_date' => env('ASAAS_INVOICE_DAYS_BEFORE', 5),

        // Default effective date period for subscription invoices
        'effective_date_period' => env('ASAAS_INVOICE_EFFECTIVE_DATE_PERIOD', 'ON_PAYMENT_CONFIRMATION'),

        // Default service description for invoices
        'default_service_description' => env('ASAAS_INVOICE_SERVICE_DESCRIPTION'),

        // Default observations for invoices
        'default_observations' => env('ASAAS_INVOICE_OBSERVATIONS'),

        // Default taxes configuration
        'taxes' => [
            'retain_iss' => env('ASAAS_INVOICE_RETAIN_ISS', false),
            'iss' => env('ASAAS_INVOICE_ISS', 0),
            'cofins' => env('ASAAS_INVOICE_COFINS', 0),
            'csll' => env('ASAAS_INVOICE_CSLL', 0),
            'inss' => env('ASAAS_INVOICE_INSS', 0),
            'ir' => env('ASAAS_INVOICE_IR', 0),
            'pis' => env('ASAAS_INVOICE_PIS', 0),
        ],

        // Default municipal service configuration
        'municipal_service_id' => env('ASAAS_INVOICE_MUNICIPAL_SERVICE_ID'),
        'municipal_service_code' => env('ASAAS_INVOICE_MUNICIPAL_SERVICE_CODE'),
        'municipal_service_name' => env('ASAAS_INVOICE_MUNICIPAL_SERVICE_NAME'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Checkout Settings
    |--------------------------------------------------------------------------
    |
    | Configure default checkout session settings.
    |
    */

    'checkout' => [
        // Default redirect URLs for checkout sessions
        'success_url' => env('ASAAS_CHECKOUT_SUCCESS_URL'),
        'cancel_url' => env('ASAAS_CHECKOUT_CANCEL_URL'),
        'expired_url' => env('ASAAS_CHECKOUT_EXPIRED_URL'),

        // Default expiration time in minutes (null = no expiration)
        'expiration_minutes' => env('ASAAS_CHECKOUT_EXPIRATION', null),

        // Default billing types (payment methods) - null means all types allowed
        // When null, CheckoutBuilder will use: PIX, CREDIT_CARD, BOLETO
        'default_billing_types' => null,
    ],

];
