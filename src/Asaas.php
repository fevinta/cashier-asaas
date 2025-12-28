<?php

declare(strict_types=1);

namespace Fevinta\CashierAsaas;

use Fevinta\CashierAsaas\Api\CustomerApi;
use Fevinta\CashierAsaas\Api\PaymentApi;
use Fevinta\CashierAsaas\Api\SubscriptionApi;
use Fevinta\CashierAsaas\Api\WebhookApi;
use Illuminate\Support\Facades\Http;

class Asaas
{
    protected static ?string $apiKey = null;

    protected static bool $sandbox = false;

    /**
     * Set the API key.
     */
    public static function setApiKey(string $key): void
    {
        static::$apiKey = $key;
    }

    /**
     * Set sandbox mode.
     */
    public static function useSandbox(bool $sandbox = true): void
    {
        static::$sandbox = $sandbox;
    }

    /**
     * Get the base URL.
     */
    public static function baseUrl(): string
    {
        if (static::$sandbox || config('cashier-asaas.sandbox', false)) {
            return 'https://sandbox.asaas.com/api/v3';
        }

        return 'https://api.asaas.com/v3';
    }

    /**
     * Get the API key.
     */
    public static function apiKey(): string
    {
        return static::$apiKey ?? config('cashier-asaas.api_key');
    }

    /**
     * Make an HTTP client configured for Asaas.
     */
    public static function client()
    {
        return Http::baseUrl(static::baseUrl())
            ->withHeaders([
                'access_token' => static::apiKey(),
                'Content-Type' => 'application/json',
            ])
            ->timeout(60);
    }

    /**
     * Customer API.
     */
    public static function customer(): CustomerApi
    {
        return new CustomerApi;
    }

    /**
     * Subscription API.
     */
    public static function subscription(): SubscriptionApi
    {
        return new SubscriptionApi;
    }

    /**
     * Payment API.
     */
    public static function payment(): PaymentApi
    {
        return new PaymentApi;
    }

    /**
     * Webhook API.
     */
    public static function webhook(): WebhookApi
    {
        return new WebhookApi;
    }
}
