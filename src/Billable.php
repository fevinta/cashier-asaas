<?php

declare(strict_types=1);

namespace FernandoHS\CashierAsaas;

use FernandoHS\CashierAsaas\Concerns\ManagesCustomer;
use FernandoHS\CashierAsaas\Concerns\ManagesPaymentMethods;
use FernandoHS\CashierAsaas\Concerns\ManagesSubscriptions;
use FernandoHS\CashierAsaas\Concerns\PerformsCharges;

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
    use ManagesCustomer;
    use ManagesPaymentMethods;
    use ManagesSubscriptions;
    use PerformsCharges;
}
