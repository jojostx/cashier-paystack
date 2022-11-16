<?php

namespace Tests\Unit;

use Jojostx\CashierPaystack\Payment;
use Tests\TestCase;

class PaymentTest extends TestCase
{
    public function test_payment_returns_false_for_invalid_transaction()
    {
        $this->assertFalse(Payment::hasValidTransaction(env('PAYSTACK_TRANSACTION_REF_INVALID')));
    }

    public function test_payment_returns_valid_transaction()
    {
        $this->assertTrue(Payment::hasValidTransaction(env('PAYSTACK_TRANSACTION_REF')));
    }
}
