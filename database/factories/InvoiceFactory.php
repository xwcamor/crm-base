<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\Invoice;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class InvoiceFactory extends Factory
{
    protected $model = Invoice::class;

    public function definition(): array
    {
        static $counter = 0;
        $counter++;
        $year = now()->year;

        return [
            'slug'          => Str::random(22),
            'number'        => sprintf('INV-%d-%05d', $year, $counter),
            'prefix'        => 'INV',
            'reference'     => 'REF-' . strtoupper(Str::random(6)),
            'status'        => 'draft',
            'company_id'    => Company::factory(),
            'issue_date'    => now()->toDateString(),
            'due_date'      => now()->addDays(30)->toDateString(),
            'currency_code' => 'USD',
            'subtotal'      => 0,
            'tax_total'     => 0,
            'grand_total'   => 0,
            'amount_paid'   => 0,
            'balance_due'   => 0,
            'is_active'     => true,
        ];
    }

    /** Helper para tests que necesitan un numero especifico (asserts por number). */
    public function withNumber(string $number): self
    {
        return $this->state(fn () => ['number' => $number]);
    }

    /** Helper para crear invoices inactivos en tests de filtro. */
    public function inactive(): self
    {
        return $this->state(fn () => ['is_active' => false]);
    }
}
