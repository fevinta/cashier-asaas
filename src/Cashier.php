<?php

declare(strict_types=1);

namespace FernandoHS\CashierAsaas;

use Money\Currencies\ISOCurrencies;
use Money\Currency;
use Money\Formatter\IntlMoneyFormatter;
use Money\Money;
use NumberFormatter;

class Cashier
{
    /**
     * Indicates if Cashier routes will be registered.
     */
    public static bool $registersRoutes = true;

    /**
     * The custom currency formatter.
     */
    protected static ?\Closure $formatCurrencyUsing = null;

    /**
     * Indicates if Cashier will mark past due subscriptions as inactive.
     */
    public static bool $deactivatePastDue = true;

    /**
     * Configure Cashier to not register its routes.
     */
    public static function ignoreRoutes(): void
    {
        static::$registersRoutes = false;
    }

    /**
     * Set the custom currency formatter.
     */
    public static function formatCurrencyUsing(?callable $callback): void
    {
        static::$formatCurrencyUsing = $callback ? \Closure::fromCallable($callback) : null;
    }

    /**
     * Format the given amount into a displayable currency.
     */
    public static function formatAmount(int|float $amount, ?string $currency = null): string
    {
        if (static::$formatCurrencyUsing) {
            return call_user_func(static::$formatCurrencyUsing, $amount, $currency);
        }

        $currency = $currency ?? config('cashier-asaas.currency', 'BRL');
        $locale = config('cashier-asaas.currency_locale', 'pt_BR');

        $money = new Money((int) ($amount * 100), new Currency($currency));
        
        $numberFormatter = new NumberFormatter($locale, NumberFormatter::CURRENCY);
        $moneyFormatter = new IntlMoneyFormatter($numberFormatter, new ISOCurrencies());

        return $moneyFormatter->format($money);
    }

    /**
     * Configure Cashier to maintain past due subscriptions as active.
     */
    public static function keepPastDueSubscriptionsActive(): void
    {
        static::$deactivatePastDue = false;
    }

    /**
     * Use a custom model for subscriptions.
     */
    public static function useSubscriptionModel(string $model): void
    {
        app()->bind(Subscription::class, $model);
    }

    /**
     * Use a custom model for payments.
     */
    public static function usePaymentModel(string $model): void
    {
        app()->bind(Payment::class, $model);
    }
}
