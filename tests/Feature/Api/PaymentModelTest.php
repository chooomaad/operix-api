<?php

namespace Tests\Feature\Api;

use App\Models\Order;
use App\Models\Payment;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PaymentModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_duplicate_provider_transaction_is_rejected_at_db_level(): void
    {
        $order = Order::factory()->create();

        Payment::create([
            'order_id'                => $order->id,
            'provider'                => 'fake',
            'provider_transaction_id' => 'tx_1',
            'amount'                  => 4900,
            'currency'                => 'EUR',
            'status'                  => 'succeeded',
        ]);

        // Même (provider, transaction_id) → violation d'unicité (anti-replay).
        $this->expectException(QueryException::class);

        Payment::create([
            'order_id'                => $order->id,
            'provider'                => 'fake',
            'provider_transaction_id' => 'tx_1',
            'amount'                  => 4900,
            'currency'                => 'EUR',
            'status'                  => 'succeeded',
        ]);
    }
}
