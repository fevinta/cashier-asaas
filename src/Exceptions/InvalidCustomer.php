<?php

declare(strict_types=1);

namespace Fevinta\CashierAsaas\Exceptions;

use Illuminate\Database\Eloquent\Model;

/**
 * Exception thrown when operating on a non-existent customer.
 */
class InvalidCustomer extends CashierException
{
    /**
     * The billable model instance.
     */
    public readonly Model $owner;

    /**
     * Create a new exception instance.
     */
    public function __construct(Model $owner, string $message)
    {
        $this->owner = $owner;

        parent::__construct($message);
    }

    /**
     * Create a new exception for a customer that has not been created yet.
     */
    public static function notYetCreated(Model $owner): self
    {
        $class = $owner::class;

        return new self(
            $owner,
            "Customer has not been created yet for model [{$class}:{$owner->getKey()}]. ".
            'Please create the customer using createAsAsaasCustomer() first.'
        );
    }

    /**
     * Create a new exception for a customer that could not be found.
     */
    public static function notFound(Model $owner): self
    {
        $class = $owner::class;

        return new self(
            $owner,
            "Customer could not be found in Asaas for model [{$class}:{$owner->getKey()}]."
        );
    }
}
