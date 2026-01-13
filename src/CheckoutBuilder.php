<?php

declare(strict_types=1);

namespace Fevinta\CashierAsaas;

use Fevinta\CashierAsaas\Enums\BillingType;
use Fevinta\CashierAsaas\Enums\ChargeType;
use Fevinta\CashierAsaas\Enums\SubscriptionCycle;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

class CheckoutBuilder
{
    protected ?Model $owner = null;

    protected bool $sendCustomerId = true;

    /** @var array<int, array{name: string, value: float, quantity: int, description?: string}> */
    protected array $items = [];

    /** @var BillingType[] */
    protected array $billingTypes = [];

    protected ChargeType $chargeType = ChargeType::DETACHED;

    protected ?int $installmentCount = null;

    protected ?float $installmentValue = null;

    protected ?SubscriptionCycle $subscriptionCycle = null;

    protected ?string $subscriptionEndDate = null;

    protected ?string $subscriptionNextDueDate = null;

    protected ?string $successUrl = null;

    protected ?string $cancelUrl = null;

    protected ?string $expiredUrl = null;

    protected ?int $expirationMinutes = null;

    /** @var array<string, mixed> */
    protected array $customerData = [];

    /** @var array<int, array{walletId: string, fixedValue?: float, percentualValue?: float}> */
    protected array $split = [];

    protected ?string $externalReference = null;

    protected ?string $description = null;

    /** @var array<string, mixed> */
    protected array $metadata = [];

    protected ?int $maxInstallmentCount = null;

    protected ?int $dueDateLimitDays = null;

    /**
     * Create a new CheckoutBuilder instance.
     */
    public function __construct(?Model $owner = null)
    {
        $this->owner = $owner;
    }

    /**
     * Create a builder for guest checkout.
     *
     * Creates a completely anonymous checkout with NO owner reference.
     * Use this when you don't need to track who made the purchase.
     *
     * See asGuest() method documentation for the difference between
     * guest() and customer()->asGuest().
     */
    public static function guest(): self
    {
        return new self;
    }

    /**
     * Create a builder for customer checkout.
     *
     * By default, this will send the customer ID to Asaas (requires complete
     * customer data in Asaas, including address). Use ->asGuest() after this
     * if you want to keep the owner reference but let the customer fill in
     * their data on the checkout page.
     */
    public static function customer(Model $owner): self
    {
        return new self($owner);
    }

    // =========================================================================
    // Item Configuration
    // =========================================================================

    /**
     * Add an item to the checkout.
     */
    public function addItem(string $name, float $value, int $quantity = 1, ?string $description = null): self
    {
        $item = [
            'name' => $name,
            'value' => $value,
            'quantity' => $quantity,
        ];

        if ($description !== null) {
            $item['description'] = $description;
        }

        $this->items[] = $item;

        return $this;
    }

    /**
     * Set multiple items at once.
     *
     * @param  array<int, array{name: string, value: float, quantity?: int, description?: string}>  $items
     */
    public function items(array $items): self
    {
        $this->items = [];

        foreach ($items as $item) {
            $this->addItem(
                $item['name'],
                $item['value'],
                $item['quantity'] ?? 1,
                $item['description'] ?? null
            );
        }

        return $this;
    }

    /**
     * Shortcut: single charge (like Stripe's checkoutCharge).
     */
    public function charge(float $amount, string $description, int $quantity = 1): self
    {
        return $this->addItem($description, $amount, $quantity);
    }

    // =========================================================================
    // Payment Method Configuration
    // =========================================================================

    /**
     * Allow all payment methods (PIX, BOLETO, CREDIT_CARD).
     */
    public function allowAllPaymentMethods(): self
    {
        $this->billingTypes = [
            BillingType::PIX,
            BillingType::CREDIT_CARD,
            BillingType::BOLETO,
        ];

        return $this;
    }

    /**
     * Only allow specific payment methods.
     *
     * @param  BillingType[]  $billingTypes
     */
    public function allowPaymentMethods(array $billingTypes): self
    {
        $this->billingTypes = $billingTypes;

        return $this;
    }

    /**
     * Only allow PIX.
     */
    public function onlyPix(): self
    {
        $this->billingTypes = [BillingType::PIX];

        return $this;
    }

    /**
     * Only allow Boleto.
     */
    public function onlyBoleto(): self
    {
        $this->billingTypes = [BillingType::BOLETO];

        return $this;
    }

    /**
     * Only allow Credit Card.
     */
    public function onlyCreditCard(): self
    {
        $this->billingTypes = [BillingType::CREDIT_CARD];

        return $this;
    }

    /**
     * Allow PIX as a payment option.
     */
    public function withPix(): self
    {
        if (! in_array(BillingType::PIX, $this->billingTypes, true)) {
            $this->billingTypes[] = BillingType::PIX;
        }

        return $this;
    }

    /**
     * Allow Boleto as a payment option.
     */
    public function withBoleto(): self
    {
        if (! in_array(BillingType::BOLETO, $this->billingTypes, true)) {
            $this->billingTypes[] = BillingType::BOLETO;
        }

        return $this;
    }

    /**
     * Allow Credit Card as a payment option.
     */
    public function withCreditCard(): self
    {
        if (! in_array(BillingType::CREDIT_CARD, $this->billingTypes, true)) {
            $this->billingTypes[] = BillingType::CREDIT_CARD;
        }

        return $this;
    }

    // =========================================================================
    // Charge Type Configuration
    // =========================================================================

    /**
     * Set the charge type.
     */
    public function chargeType(ChargeType $type): self
    {
        $this->chargeType = $type;

        return $this;
    }

    /**
     * Set as one-time payment (DETACHED).
     */
    public function oneTime(): self
    {
        $this->chargeType = ChargeType::DETACHED;

        return $this;
    }

    /**
     * Set as installment payment.
     */
    public function installments(int $count, ?float $installmentValue = null): self
    {
        $this->chargeType = ChargeType::INSTALLMENT;
        $this->installmentCount = $count;
        $this->installmentValue = $installmentValue;

        return $this;
    }

    /**
     * Set maximum installment count (customer chooses).
     */
    public function maxInstallments(int $count): self
    {
        $this->chargeType = ChargeType::INSTALLMENT;
        $this->maxInstallmentCount = $count;

        return $this;
    }

    /**
     * Set as recurring payment (subscription).
     */
    public function recurring(SubscriptionCycle $cycle): self
    {
        $this->chargeType = ChargeType::RECURRENT;
        $this->subscriptionCycle = $cycle;

        return $this;
    }

    /**
     * Set subscription cycle.
     */
    public function cycle(SubscriptionCycle $cycle): self
    {
        $this->subscriptionCycle = $cycle;

        return $this;
    }

    /**
     * Monthly recurring.
     */
    public function monthly(): self
    {
        return $this->recurring(SubscriptionCycle::MONTHLY);
    }

    /**
     * Yearly recurring.
     */
    public function yearly(): self
    {
        return $this->recurring(SubscriptionCycle::YEARLY);
    }

    /**
     * Weekly recurring.
     */
    public function weekly(): self
    {
        return $this->recurring(SubscriptionCycle::WEEKLY);
    }

    /**
     * Biweekly recurring.
     */
    public function biweekly(): self
    {
        return $this->recurring(SubscriptionCycle::BIWEEKLY);
    }

    /**
     * Quarterly recurring.
     */
    public function quarterly(): self
    {
        return $this->recurring(SubscriptionCycle::QUARTERLY);
    }

    /**
     * Semiannually recurring.
     */
    public function semiannually(): self
    {
        return $this->recurring(SubscriptionCycle::SEMIANNUALLY);
    }

    /**
     * Set subscription end date.
     */
    public function subscriptionEndDate(string $endDate): self
    {
        $this->subscriptionEndDate = $endDate;

        return $this;
    }

    /**
     * Set subscription next due date.
     */
    public function subscriptionNextDueDate(string $nextDueDate): self
    {
        $this->subscriptionNextDueDate = $nextDueDate;

        return $this;
    }

    // =========================================================================
    // URL Configuration
    // =========================================================================

    /**
     * Set the success redirect URL.
     */
    public function successUrl(string $url): self
    {
        $this->successUrl = $url;

        return $this;
    }

    /**
     * Set the cancel redirect URL.
     */
    public function cancelUrl(string $url): self
    {
        $this->cancelUrl = $url;

        return $this;
    }

    /**
     * Set the expired redirect URL.
     */
    public function expiredUrl(string $url): self
    {
        $this->expiredUrl = $url;

        return $this;
    }

    // =========================================================================
    // Session Configuration
    // =========================================================================

    /**
     * Set checkout expiration time in minutes.
     */
    public function expiresIn(int $minutes): self
    {
        $this->expirationMinutes = $minutes;

        return $this;
    }

    /**
     * Set due date limit days for boleto payments.
     */
    public function dueDateLimitDays(int $days): self
    {
        $this->dueDateLimitDays = $days;

        return $this;
    }

    /**
     * Set external reference.
     */
    public function externalReference(string $reference): self
    {
        $this->externalReference = $reference;

        return $this;
    }

    /**
     * Set description.
     */
    public function description(string $description): self
    {
        $this->description = $description;

        return $this;
    }

    /**
     * Add metadata.
     *
     * @param  array<string, mixed>  $metadata
     */
    public function withMetadata(array $metadata): self
    {
        $this->metadata = array_merge($this->metadata, $metadata);

        return $this;
    }

    // =========================================================================
    // Guest Customer Data
    // =========================================================================

    /**
     * Allow customer to fill data on checkout page instead of using saved customer.
     *
     * IMPORTANT: Understand the difference between guest() and asGuest():
     *
     * - guest() → Creates checkout with NO owner at all (completely anonymous)
     *   Use when: You don't need to track who made the purchase
     *   Example: CheckoutBuilder::guest()
     *
     * - asGuest() → Has owner for internal tracking, but doesn't send customer ID to Asaas
     *   Use when: You need to track the user internally (webhooks, orders) but the user
     *             doesn't have complete data in Asaas (missing address, etc.)
     *   Example: CheckoutBuilder::customer($user)->asGuest()
     *
     * This method is useful when:
     * - You have an owner but the customer doesn't have address data yet
     * - You want them to fill in/review their information during checkout
     * - You need internal tracking but can't send customer ID to Asaas
     */
    public function asGuest(): self
    {
        $this->sendCustomerId = false;

        return $this;
    }

    /**
     * Set customer data for guest checkout.
     *
     * @param  array<string, mixed>  $data
     */
    public function customerData(array $data): self
    {
        $this->customerData = array_merge($this->customerData, $data);

        return $this;
    }

    /**
     * Set customer name (guest checkout).
     */
    public function customerName(string $name): self
    {
        $this->customerData['name'] = $name;

        return $this;
    }

    /**
     * Set customer CPF/CNPJ (guest checkout).
     */
    public function customerCpfCnpj(string $cpfCnpj): self
    {
        $this->customerData['cpfCnpj'] = $cpfCnpj;

        return $this;
    }

    /**
     * Set customer email (guest checkout).
     */
    public function customerEmail(string $email): self
    {
        $this->customerData['email'] = $email;

        return $this;
    }

    /**
     * Set customer phone (guest checkout).
     */
    public function customerPhone(string $phone): self
    {
        $this->customerData['phone'] = $phone;

        return $this;
    }

    /**
     * Set customer mobile phone (guest checkout).
     */
    public function customerMobilePhone(string $mobilePhone): self
    {
        $this->customerData['mobilePhone'] = $mobilePhone;

        return $this;
    }

    /**
     * Set customer address (guest checkout).
     */
    public function customerAddress(
        string $address,
        string $addressNumber,
        string $postalCode,
        string $province,
        string $city,
        ?string $complement = null
    ): self {
        $this->customerData['address'] = $address;
        $this->customerData['addressNumber'] = $addressNumber;
        $this->customerData['postalCode'] = $postalCode;
        $this->customerData['province'] = $province;
        $this->customerData['city'] = $city;

        if ($complement !== null) {
            $this->customerData['complement'] = $complement;
        }

        return $this;
    }

    // =========================================================================
    // Split Payment
    // =========================================================================

    /**
     * Add payment split configuration.
     */
    public function split(string $walletId, ?float $fixedValue = null, ?float $percentualValue = null): self
    {
        $split = ['walletId' => $walletId];

        if ($fixedValue !== null) {
            $split['fixedValue'] = $fixedValue;
        }

        if ($percentualValue !== null) {
            $split['percentualValue'] = $percentualValue;
        }

        $this->split[] = $split;

        return $this;
    }

    // =========================================================================
    // Creation
    // =========================================================================

    /**
     * Create the checkout session.
     *
     * @param  array<string, mixed>  $sessionOptions  Additional options to merge
     */
    public function create(array $sessionOptions = []): Checkout
    {
        $this->validate();

        $payload = $this->buildPayload();
        $payload = array_merge($payload, $sessionOptions);

        $response = Asaas::checkout()->create($payload);

        $id = $response['id'];
        $url = Checkout::formatUrl($id);

        return new Checkout($id, $url, $response);
    }

    /**
     * Build the payload for Asaas API.
     *
     * @return array<string, mixed>
     */
    protected function buildPayload(): array
    {
        $payload = [
            'chargeTypes' => [$this->chargeType->value],
        ];

        // Billing types (payment methods)
        $billingTypes = $this->billingTypes ?: [
            BillingType::PIX,
            BillingType::CREDIT_CARD,
            BillingType::BOLETO,
        ];
        $payload['billingTypes'] = array_map(fn(BillingType $type) => $type->value, $billingTypes);

        // Items
        if (! empty($this->items)) {
            $payload['items'] = $this->items;
        }

        // Customer handling
        if ($this->owner !== null && $this->sendCustomerId) {
            // Ensure customer exists in Asaas
            if (method_exists($this->owner, 'createOrGetAsaasCustomer')) {
                $this->owner->createOrGetAsaasCustomer();
            }

            if (isset($this->owner->asaas_id)) {
                $payload['customer'] = $this->owner->asaas_id;
            }
        } elseif (! empty($this->customerData)) {
            $payload['customerData'] = $this->customerData;
        }
        // If sendCustomerId is false, customer will fill data on checkout page

        // Callback URLs
        $callback = [];

        $successUrl = $this->successUrl ?? config('cashier-asaas.checkout.success_url');
        if ($successUrl) {
            $callback['successUrl'] = $successUrl;
        }

        $cancelUrl = $this->cancelUrl ?? config('cashier-asaas.checkout.cancel_url');
        if ($cancelUrl) {
            $callback['cancelUrl'] = $cancelUrl;
        }

        $expiredUrl = $this->expiredUrl ?? config('cashier-asaas.checkout.expired_url');
        if ($expiredUrl) {
            $callback['expiredUrl'] = $expiredUrl;
        }

        if (! empty($callback)) {
            $payload['callback'] = $callback;
        }

        // Installments
        if ($this->chargeType === ChargeType::INSTALLMENT) {
            if ($this->installmentCount !== null) {
                $payload['installmentCount'] = $this->installmentCount;
            }
            if ($this->installmentValue !== null) {
                $payload['installmentValue'] = $this->installmentValue;
            }
            if ($this->maxInstallmentCount !== null) {
                $payload['maxInstallmentCount'] = $this->maxInstallmentCount;
            }
        }

        // Recurring/Subscription
        if ($this->chargeType === ChargeType::RECURRENT && $this->subscriptionCycle !== null) {
            $subscription = [
                'cycle' => $this->subscriptionCycle->value,
            ];

            // nextDueDate is required - default to today if not set
            if ($this->subscriptionNextDueDate !== null) {
                $subscription['nextDueDate'] = $this->subscriptionNextDueDate;
            } else {
                $subscription['nextDueDate'] = now()->format('Y-m-d');
            }

            // endDate is optional
            if ($this->subscriptionEndDate !== null) {
                $subscription['endDate'] = $this->subscriptionEndDate;
            }

            $payload['subscription'] = $subscription;
        }

        // Expiration
        $expirationMinutes = $this->expirationMinutes ?? config('cashier-asaas.checkout.expiration_minutes');
        if ($expirationMinutes !== null) {
            $payload['expirationMinutes'] = $expirationMinutes;
        }

        // Due date limit for boleto
        if ($this->dueDateLimitDays !== null) {
            $payload['dueDateLimitDays'] = $this->dueDateLimitDays;
        }

        // External reference
        if ($this->externalReference !== null) {
            $payload['externalReference'] = $this->externalReference;
        }

        // Description
        if ($this->description !== null) {
            $payload['description'] = $this->description;
        }

        // Split payment
        if (! empty($this->split)) {
            $payload['split'] = $this->split;
        }

        // Metadata
        if (! empty($this->metadata)) {
            $payload['metadata'] = $this->metadata;
        }

        return $payload;
    }

    /**
     * Validate the builder configuration.
     *
     * @throws InvalidArgumentException
     */
    protected function validate(): void
    {
        if (empty($this->items)) {
            throw new InvalidArgumentException('At least one item is required for checkout.');
        }

        if ($this->chargeType === ChargeType::INSTALLMENT) {
            // Check if credit card is allowed (installments only work with credit card)
            if (! empty($this->billingTypes) && ! in_array(BillingType::CREDIT_CARD, $this->billingTypes, true)) {
                throw new InvalidArgumentException('Installment payments require credit card as a payment method.');
            }
        }

        if ($this->chargeType === ChargeType::RECURRENT && $this->subscriptionCycle === null) {
            throw new InvalidArgumentException('Subscription cycle is required for recurring checkouts.');
        }

        // Guest checkout requires customer data
        if ($this->owner === null && empty($this->customerData)) {
            // This is allowed - the customer will enter their data on the checkout page
        }
    }
}
