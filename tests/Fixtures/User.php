<?php

declare(strict_types=1);

namespace Fevinta\CashierAsaas\Tests\Fixtures;

use Fevinta\CashierAsaas\Billable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;

/**
 * Test user model with Billable trait.
 *
 * @property int $id
 * @property string $name
 * @property string $email
 * @property string|null $cpf_cnpj
 * @property string|null $phone
 * @property string|null $asaas_id
 * @property \Carbon\Carbon|null $trial_ends_at
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 */
class User extends Authenticatable
{
    use Billable;
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'trial_ends_at' => 'datetime',
    ];
}
