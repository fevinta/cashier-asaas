<?php

declare(strict_types=1);

namespace Fevinta\CashierAsaas\Database\Factories;

use Fevinta\CashierAsaas\Tests\Fixtures\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Factory for creating User models in tests.
 *
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    protected $model = User::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->name(),
            'email' => $this->faker->unique()->safeEmail(),
            'cpf_cnpj' => $this->generateCpf(),
            'phone' => '11'.$this->faker->numerify('#########'),
            'asaas_id' => null,
            'trial_ends_at' => null,
        ];
    }

    /**
     * Indicate that the user has an Asaas ID.
     */
    public function withAsaasId(?string $asaasId = null): self
    {
        return $this->state(fn (array $attributes) => [
            'asaas_id' => $asaasId ?? 'cus_'.$this->faker->uuid(),
        ]);
    }

    /**
     * Indicate that the user is on a generic trial.
     */
    public function onGenericTrial(int $days = 14): self
    {
        return $this->state(fn (array $attributes) => [
            'trial_ends_at' => now()->addDays($days),
        ]);
    }

    /**
     * Indicate that the user's trial has expired.
     */
    public function expiredTrial(): self
    {
        return $this->state(fn (array $attributes) => [
            'trial_ends_at' => now()->subDay(),
        ]);
    }

    /**
     * Generate a valid CPF number.
     */
    protected function generateCpf(): string
    {
        $n = [];
        for ($i = 0; $i < 9; $i++) {
            $n[$i] = random_int(0, 9);
        }

        // First check digit
        $d1 = 0;
        for ($i = 0; $i < 9; $i++) {
            $d1 += $n[$i] * (10 - $i);
        }
        $d1 = 11 - ($d1 % 11);
        if ($d1 >= 10) {
            $d1 = 0;
        }
        $n[9] = $d1;

        // Second check digit
        $d2 = 0;
        for ($i = 0; $i < 10; $i++) {
            $d2 += $n[$i] * (11 - $i);
        }
        $d2 = 11 - ($d2 % 11);
        if ($d2 >= 10) {
            $d2 = 0;
        }
        $n[10] = $d2;

        return implode('', $n);
    }
}
