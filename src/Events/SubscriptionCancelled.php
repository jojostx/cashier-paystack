<?php

namespace Jojostx\CashierPaystack\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Jojostx\CashierPaystack\Subscription;

class SubscriptionCancelled
{
    use Dispatchable, SerializesModels;

    /**
     * The subscription instance.
     *
     * @var \Jojostx\CashierPaystack\Subscription
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
     * @param  \Jojostx\CashierPaystack\Subscription  $subscription
     * @param  array  $payload
     * @return void
     */
    public function __construct(Subscription $subscription, array $payload)
    {
        $this->subscription = $subscription;
        $this->payload = $payload;
    }
}