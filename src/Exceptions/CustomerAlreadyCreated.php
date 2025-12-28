<?php

declare(strict_types=1);

namespace Fevinta\CashierAsaas\Exceptions;

use Illuminate\Database\Eloquent\Model;

/**
 * Exception thrown when attempting to create an Asaas customer that already exists.
 */
class CustomerAlreadyCreated extends CashierException
{
    /**
     * The billable model instance.
     */
    public readonly Model $owner;

    /**
     * Create a new exception instance.
     */
    public function __construct(Model $owner)
    {
        $this->owner = $owner;

        $class = $owner::class;
        parent::__construct(
            "Customer [{$owner->asaasId()}] already exists for model [{$class}:{$owner->getKey()}]."
        );
    }

    /**
     * Create a new exception for an existing customer.
     */
    public static function exists(Model $owner): self
    {
        return new self($owner);
    }
}
