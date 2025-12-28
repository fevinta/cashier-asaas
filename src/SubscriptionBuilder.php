<?php

declare(strict_types=1);

namespace FernandoHS\CashierAsaas;

use Carbon\Carbon;
use FernandoHS\CashierAsaas\Enums\BillingType;
use FernandoHS\CashierAsaas\Enums\SubscriptionCycle;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

class SubscriptionBuilder
{
    protected Model $owner;
    protected string $type;
    protected string $plan;
    protected ?float $value = null;
    protected SubscriptionCycle $cycle = SubscriptionCycle::MONTHLY;
    protected BillingType $billingType = BillingType::UNDEFINED;
    protected ?Carbon $nextDueDate = null;
    protected ?string $description = null;
    protected ?int $trialDays = null;
    protected ?Carbon $trialEndsAt = null;
    protected ?array $creditCard = null;
    protected ?array $creditCardHolderInfo = null;
    protected ?string $creditCardToken = null;
    protected ?string $remoteIp = null;
    protected array $metadata = [];
    protected array $split = [];
    protected bool $updatePendingPayments = false;
    protected ?int $maxPayments = null;
    protected ?Carbon $endDate = null;
    protected ?float $discount = null;
    protected ?float $interest = null;
    protected ?float $fine = null;

    public function __construct(Model $owner, string $type, string $plan)
    {
        $this->owner = $owner;
        $this->type = $type;
        $this->plan = $plan;
    }

    /**
     * Set the subscription value (price).
     */
    public function price(float $value): self
    {
        $this->value = $value;
        return $this;
    }

    /**
     * Set the billing cycle.
     */
    public function cycle(SubscriptionCycle $cycle): self
    {
        $this->cycle = $cycle;
        return $this;
    }

    /**
     * Set monthly billing cycle.
     */
    public function monthly(): self
    {
        return $this->cycle(SubscriptionCycle::MONTHLY);
    }

    /**
     * Set yearly billing cycle.
     */
    public function yearly(): self
    {
        return $this->cycle(SubscriptionCycle::YEARLY);
    }

    /**
     * Set weekly billing cycle.
     */
    public function weekly(): self
    {
        return $this->cycle(SubscriptionCycle::WEEKLY);
    }

    /**
     * Set quarterly billing cycle.
     */
    public function quarterly(): self
    {
        return $this->cycle(SubscriptionCycle::QUARTERLY);
    }

    /**
     * Set the billing type (PIX, BOLETO, CREDIT_CARD, UNDEFINED).
     */
    public function billingType(BillingType $type): self
    {
        $this->billingType = $type;
        return $this;
    }

    /**
     * Set billing type to credit card.
     */
    public function withCreditCard(
        ?array $creditCard = null,
        ?array $holderInfo = null,
        ?string $remoteIp = null
    ): self {
        $this->billingType = BillingType::CREDIT_CARD;
        
        if ($creditCard) {
            $this->creditCard = $creditCard;
        }
        
        if ($holderInfo) {
            $this->creditCardHolderInfo = $holderInfo;
        }
        
        $this->remoteIp = $remoteIp ?? request()->ip();
        
        return $this;
    }

    /**
     * Use a tokenized credit card.
     */
    public function withCreditCardToken(string $token, ?string $remoteIp = null): self
    {
        $this->billingType = BillingType::CREDIT_CARD;
        $this->creditCardToken = $token;
        $this->remoteIp = $remoteIp ?? request()->ip();
        
        return $this;
    }

    /**
     * Set billing type to boleto.
     */
    public function withBoleto(): self
    {
        $this->billingType = BillingType::BOLETO;
        return $this;
    }

    /**
     * Set billing type to PIX.
     */
    public function withPix(): self
    {
        $this->billingType = BillingType::PIX;
        return $this;
    }

    /**
     * Let customer choose payment method.
     */
    public function askCustomer(): self
    {
        $this->billingType = BillingType::UNDEFINED;
        return $this;
    }

    /**
     * Set the date of the first payment.
     */
    public function startsAt(Carbon $date): self
    {
        $this->nextDueDate = $date;
        return $this;
    }

    /**
     * Set the subscription to start immediately.
     */
    public function startImmediately(): self
    {
        $this->nextDueDate = Carbon::today();
        return $this;
    }

    /**
     * Add trial days.
     */
    public function trialDays(int $days): self
    {
        $this->trialDays = $days;
        $this->trialEndsAt = Carbon::now()->addDays($days);
        $this->nextDueDate = $this->trialEndsAt;
        
        return $this;
    }

    /**
     * Set trial to end at a specific date.
     */
    public function trialUntil(Carbon $date): self
    {
        $this->trialEndsAt = $date;
        $this->nextDueDate = $date;
        
        return $this;
    }

    /**
     * Skip trial entirely.
     */
    public function skipTrial(): self
    {
        $this->trialDays = null;
        $this->trialEndsAt = null;
        
        return $this;
    }

    /**
     * Set the subscription description.
     */
    public function description(string $description): self
    {
        $this->description = $description;
        return $this;
    }

    /**
     * Set maximum number of payments (subscription end).
     */
    public function maxPayments(int $max): self
    {
        $this->maxPayments = $max;
        return $this;
    }

    /**
     * Set the subscription end date.
     */
    public function endsAt(Carbon $date): self
    {
        $this->endDate = $date;
        return $this;
    }

    /**
     * Add a discount to the subscription.
     */
    public function withDiscount(float $value, int $dueDateLimitDays = 0): self
    {
        $this->discount = $value;
        return $this;
    }

    /**
     * Add interest for late payments.
     */
    public function withInterest(float $percentage): self
    {
        $this->interest = $percentage;
        return $this;
    }

    /**
     * Add fine for late payments.
     */
    public function withFine(float $value): self
    {
        $this->fine = $value;
        return $this;
    }

    /**
     * Add payment split (revenue sharing).
     */
    public function split(string $walletId, float $fixedValue = null, float $percentualValue = null): self
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

    /**
     * Add metadata to the subscription.
     */
    public function withMetadata(array $metadata): self
    {
        $this->metadata = array_merge($this->metadata, $metadata);
        return $this;
    }

    /**
     * Create the subscription.
     */
    public function create(): Subscription
    {
        return $this->createSubscription();
    }

    /**
     * Create the subscription and return.
     */
    protected function createSubscription(): Subscription
    {
        // Ensure customer exists in Asaas
        $this->owner->createOrGetAsaasCustomer();

        // Resolve price from plan config if not set
        $value = $this->value ?? $this->resolvePlanPrice();
        
        if ($value === null) {
            throw new InvalidArgumentException('Subscription price must be set via price() or defined in config.');
        }

        // Build Asaas subscription payload
        $payload = [
            'customer' => $this->owner->asaas_id,
            'billingType' => $this->billingType->value,
            'value' => $value,
            'cycle' => $this->cycle->value,
            'description' => $this->description ?? "Subscription: {$this->plan}",
            'nextDueDate' => ($this->nextDueDate ?? Carbon::tomorrow())->format('Y-m-d'),
            'externalReference' => json_encode([
                'type' => $this->type,
                'plan' => $this->plan,
                'owner_id' => $this->owner->getKey(),
            ]),
        ];

        // Add credit card data if provided
        if ($this->billingType === BillingType::CREDIT_CARD) {
            if ($this->creditCardToken) {
                $payload['creditCardToken'] = $this->creditCardToken;
            } elseif ($this->creditCard) {
                $payload['creditCard'] = $this->creditCard;
                $payload['creditCardHolderInfo'] = $this->creditCardHolderInfo;
            }
            
            if ($this->remoteIp) {
                $payload['remoteIp'] = $this->remoteIp;
            }
        }

        // Add optional fields
        if ($this->maxPayments) {
            $payload['maxPayments'] = $this->maxPayments;
        }

        if ($this->endDate) {
            $payload['endDate'] = $this->endDate->format('Y-m-d');
        }

        if ($this->discount) {
            $payload['discount'] = [
                'value' => $this->discount,
                'dueDateLimitDays' => 0,
            ];
        }

        if ($this->interest) {
            $payload['interest'] = ['value' => $this->interest];
        }

        if ($this->fine) {
            $payload['fine'] = ['value' => $this->fine];
        }

        if (! empty($this->split)) {
            $payload['split'] = $this->split;
        }

        // Create subscription in Asaas
        $asaasSubscription = Asaas::subscription()->create($payload);

        // Create local subscription record
        $subscription = $this->owner->subscriptions()->create([
            'type' => $this->type,
            'asaas_id' => $asaasSubscription['id'],
            'asaas_status' => $asaasSubscription['status'],
            'plan' => $this->plan,
            'value' => $value,
            'cycle' => $this->cycle->value,
            'billing_type' => $this->billingType->value,
            'next_due_date' => Carbon::parse($asaasSubscription['nextDueDate']),
            'trial_ends_at' => $this->trialEndsAt,
            'ends_at' => $this->endDate,
        ]);

        return $subscription;
    }

    /**
     * Resolve price from plan configuration.
     */
    protected function resolvePlanPrice(): ?float
    {
        return config("cashier-asaas.plans.{$this->plan}.price");
    }
}
