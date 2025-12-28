<?php

declare(strict_types=1);

namespace FernandoHS\CashierAsaas\Concerns;

use FernandoHS\CashierAsaas\Asaas;

trait ManagesPaymentMethods
{
    /**
     * Tokenize a credit card for future use.
     */
    public function tokenizeCreditCard(
        array $creditCard,
        array $holderInfo,
        ?string $remoteIp = null
    ): string {
        $this->createOrGetAsaasCustomer();

        $response = Asaas::payment()->tokenize([
            'customer' => $this->asaas_id,
            'creditCard' => $creditCard,
            'creditCardHolderInfo' => $holderInfo,
            'remoteIp' => $remoteIp ?? request()->ip(),
        ]);

        return $response['creditCardToken'];
    }

    /**
     * Get stored credit card token for customer.
     * Note: Asaas stores tokens per customer, retrieved from subscription.
     */
    public function defaultPaymentMethod(): ?array
    {
        // Get from active subscription if exists
        $subscription = $this->subscription('default');
        
        if ($subscription) {
            $asaasSubscription = $subscription->asAsaasSubscription();
            
            if (isset($asaasSubscription['creditCard'])) {
                return [
                    'type' => 'credit_card',
                    'brand' => $asaasSubscription['creditCard']['creditCardBrand'] ?? null,
                    'last4' => $asaasSubscription['creditCard']['creditCardNumber'] ?? null,
                ];
            }
        }

        return null;
    }

    /**
     * Check if customer has a payment method.
     */
    public function hasPaymentMethod(): bool
    {
        return $this->defaultPaymentMethod() !== null;
    }

    /**
     * Update the default payment method for subscriptions.
     */
    public function updateDefaultPaymentMethod(
        array $creditCard,
        array $holderInfo,
        ?string $remoteIp = null
    ): self {
        // Update all active subscriptions with new card
        $this->subscriptions()
            ->where('billing_type', 'CREDIT_CARD')
            ->whereNull('ends_at')
            ->each(function ($subscription) use ($creditCard, $holderInfo, $remoteIp) {
                $subscription->updateCreditCard($creditCard, $holderInfo, $remoteIp);
            });

        return $this;
    }

    /**
     * Update the default payment method using a token.
     */
    public function updateDefaultPaymentMethodFromToken(string $token, ?string $remoteIp = null): self
    {
        $this->subscriptions()
            ->where('billing_type', 'CREDIT_CARD')
            ->whereNull('ends_at')
            ->each(function ($subscription) use ($token, $remoteIp) {
                $subscription->updateCreditCardToken($token, $remoteIp);
            });

        return $this;
    }
}
