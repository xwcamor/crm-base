<?php

namespace Database\Factories;

use App\Models\Payment;
use App\Models\PaymentMethod;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class PaymentFactory extends Factory
{
    protected $model = Payment::class;

    public function definition(): array
    {
        static $counter = 0;
        $counter++;
        $year = now()->year;

        return [
            'slug'              => Str::random(22),
            'reference'         => sprintf('PAY-%d-%05d', $year, $counter),
            'type'              => 'invoice_payment',
            'payment_method_id' => PaymentMethod::factory(),
            'amount'            => 100.00,
            'currency_code'     => 'USD',
            'paid_at'           => now(),
            'status'            => 'completed',
            'is_active'         => true,
        ];
    }

    /** Helper para tests que necesitan una reference especifica. */
    public function withReference(string $reference): self
    {
        return $this->state(fn () => ['reference' => $reference]);
    }

    /** Helper para crear payments inactivos en tests de filtro. */
    public function inactive(): self
    {
        return $this->state(fn () => ['is_active' => false]);
    }
}
