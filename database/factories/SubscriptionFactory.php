<?php

namespace Jojostx\CashierPaystack\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use Jojostx\CashierPaystack\Cashier;
use Jojostx\CashierPaystack\Models\Subscription;

class SubscriptionFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = Subscription::class;

    /**
     * Define the model's default state.
     *
     * @return array
     */
    public function definition()
    {
        $model = Cashier::$customerModel;

        return [
            (new $model)->getForeignKey() => ($model)::factory(),
            'name' => 'default',
            'paystack_id' => 'sub_'.Str::random(40),
            'paystack_status' => Subscription::STATUS_ACTIVE,
            'quantity' => null,
            'trial_ends_at' => null,
            'ends_at' => null,
        ];
    }
}