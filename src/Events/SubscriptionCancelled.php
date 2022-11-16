<?php

namespace Jojostx\Cashier\Paystack\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Jojostx\Cashier\Paystack\Subscription;

class SubscriptionCancelled
{
    use Dispatchable, SerializesModels;

    /**
     * The subscription instance.
     *
     * @var \Jojostx\Cashier\Paystack\Subscription
     */
    public $subscription;

    /**
     * The webhook payload.
     *
     * @var array
     */
    public $payload;

    /**
     * Create a new event instance.
     *
     * @param  \Jojostx\Cashier\Paystack\Subscription  $subscription
     * @param  array  $payload
     * @return void
     */
    public function __construct(Subscription $subscription, array $payload)
    {
        $this->subscription = $subscription;
        $this->payload = $payload;
    }
}