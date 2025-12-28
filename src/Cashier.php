<?php

declare(strict_types=1);

namespace Fevinta\CashierAsaas;

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
     * The subscription model class name.
     *
     * @var class-string<Subscription>
     */
    public static string $subscriptionModel = Subscription::class;

    /**
     * The payment model class name.
     *
     * @var class-string<Payment>
     */
    public static string $paymentModel = Payment::class;

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
        $moneyFormatter = new IntlMoneyFormatter($numberFormatter, new ISOCurrencies);

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
     *
     * @param  class-string<Subscription>  $model
     */
    public static function useSubscriptionModel(string $model): void
    {
        static::$subscriptionModel = $model;
    }

    /**
     * Use a custom model for payments.
     *
     * @param  class-string<Payment>  $model
     */
    public static function usePaymentModel(string $model): void
    {
        static::$paymentModel = $model;
    }

    /**
     * Get the subscription model class name.
     *
     * @return class-string<Subscription>
     */
    public static function subscriptionModel(): string
    {
        return static::$subscriptionModel;
    }

    /**
     * Get the payment model class name.
     *
     * @return class-string<Payment>
     */
    public static function paymentModel(): string
    {
        return static::$paymentModel;
    }
}
