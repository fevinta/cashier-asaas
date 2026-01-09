<?php

declare(strict_types=1);

namespace Fevinta\CashierAsaas;

use Fevinta\CashierAsaas\Concerns\ManagesCheckouts;
use Fevinta\CashierAsaas\Concerns\ManagesCustomer;
use Fevinta\CashierAsaas\Concerns\ManagesPaymentMethods;
use Fevinta\CashierAsaas\Concerns\ManagesSubscriptions;
use Fevinta\CashierAsaas\Concerns\PerformsCharges;

/**
 * Add this trait to your User model to enable Asaas billing.
 *
 * @example
 * class User extends Authenticatable
 * {
 *     use Billable;
 * }
 */
trait Billable
{
    use ManagesCheckouts;
    use ManagesCustomer;
    use ManagesPaymentMethods;
    use ManagesSubscriptions;
    use PerformsCharges;
}
