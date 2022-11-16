<?php

namespace Jojostx\Cashier\Paystack;

use Exception;
use Carbon\Carbon;
use Jojostx\Cashier\Paystack\Models\Subscription;

class SubscriptionBuilder
{
    /**
     * The model that is subscribing.
     *
     * @var \Illuminate\Database\Eloquent\Model
     */
    protected $owner;

    /**
     * The name of the subscription.
     *
     * @var string
     */
    protected string $name;

    /**
     * The name of the plan being subscribed to.
     *
     * @var string
     */
    protected string $plan;

    /**
     * The number of trial days to apply to the subscription.
     *
     * @var int|null
     */
    protected $trialDays;

    /**
     * Indicates that the trial should end immediately.
     *
     * @var bool
     */
    protected $skipTrial = false;

    /**
     * The authorization code used to create subscription.
     *
     * @var string
     */
    protected string $authorization;

    /**
     * Create a new subscription builder instance.
     *
     * @param  mixed  $owner
     * @param  string  $name
     * @param  string  $plan
     * @return void
     */
    public function __construct($owner, $name, $plan)
    {
        $this->owner = $owner;
        $this->name = $name;
        $this->plan = $plan;
    }
    /**
     * Specify the ending date of the trial.
     *
     * @param  int  $trialDays
     * @return $this
     */
    public function trialDays($trialDays)
    {
        $this->trialDays = $trialDays;
        return $this;
    }
    /**
     * Force the trial to end immediately.
     *
     * @return $this
     */
    public function skipTrial()
    {
        $this->skipTrial = true;
        return $this;
    }

    /**
     * Add a new Paystack subscription to the model.
     *
     * @param  array  $options
     * @return \Jojostx\Cashier\Paystack\Subscription
     * @throws \Exception
     */
    public function add(array $options = [])
    {
        if ($this->skipTrial) {
            $trialEndsAt = null;
        } else {
            $trialEndsAt = $this->trialDays ? Carbon::now()->addDays($this->trialDays) : null;
        }

        return $this->owner->subscriptions()->create([
            'name' => $this->name,
            'paystack_id'   => $options['id'],
            'paystack_code' => $options['subscription_code'],
            'paystack_status' => $options['status'],
            'paystack_plan' => $this->plan,
            'quantity' => 1,
            'trial_ends_at' => $trialEndsAt,
            'ends_at' => null,
        ]);
    }

    /**
     * Create a new Paystack subscription.
     *
     * @param  string|null  $token
     * @param  array  $options
     * @return \Jojostx\Cashier\Paystack\Subscription
     * @throws \Exception
     */
    public function create($token = null, array $options = [])
    {
        $payload = $this->getSubscriptionPayload(
            $this->getPaystackCustomer(),
            $options
        );
        // Set the desired authorization you wish to use for this subscription here. 
        // If this is not supplied, the customer's most recent authorization would be used
        if (isset($token)) {
            $payload['authorization'] = $token;
        }
        $subscription = PaystackService::createSubscription($payload);

        if (!$subscription['status']) {
            throw new Exception('Paystack failed to create subscription: ' . $subscription['message']);
        }

        return $this->add($subscription['data']);
    }

    /**
     * Charge for a Paystack subscription.
     *
     * @param  array  $options
     * @return \Jojostx\Cashier\Paystack\Subscription
     * @throws \Exception
     */
    public function charge(array $options = [])
    {
        $options = array_merge([
            'plan' => $this->plan
        ], $options);
        return $this->owner->charge(100, $options);
    }

    /**
     * Update the underlying Paystack subscription information for the model.
     *
     * @param  array  $options
     * @return array
     */
    public function updatePaystackSubscription(array $options)
    {
        $payload = $this->billable->PaystackOptions(array_merge([
            'subscription_id' => $this->paystack_id,
        ], $options));

        /**
         * algo:
         * 1. create a new subscription with the new plan on paystack,
         *      - where: elasped_period = present_date - curr_start_date,
         *               new_amount = new plan's subscription amount,
         *               curr_amount = current plan's subscription amount,
         *               curr_start_date = current plan's start date,
         * 
         *      - if the swap upgrades the plan, charge the customer immediately
         *          - charge = ((new_amount/plan_period) * (elasped_period)) - curr_amount

         *      - if the swap downgrades the plan,
         *          - set the new subscription's start_date to the end of the current subscription
         *          - period when creating the subscription on paystack
         *          - initiate a refund to the customer of amount = curr_amount - new_amount. {emit an event that runs the refund action}
         * 1.1, if the response from step 1 is successful,
         *      a, update the current subscription in the database with the response from step 1,
         *      b, disable the current subscription on the paystack end,
         */

        $response = $payload['response'];

        return $response;
    }

    /**
     * Get the subscription payload data for Paystack.
     *
     * @param  $customer
     * @param  array  $options
     * @return array
     * @throws \Exception
     */
    protected function getSubscriptionPayload($customer, array $options = [])
    {
        if ($this->skipTrial) {
            $startDate = Carbon::now();
        } else {
            $startDate =  $this->trialDays ? Carbon::now()->addDays($this->trialDays) : Carbon::now();
        }

        return [
            "customer" => $customer['customer_code'], //Customer email or code
            "plan" => $this->plan,
            "start_date" => $startDate->format('c'),
        ];
    }
    /**
     * Get the Paystack customer instance for the current user and token.
     *
     * @param  array  $options
     * @return $customer
     */
    protected function getPaystackCustomer(array $options = [])
    {
        if (!$this->owner->paystack_id) {
            $customer = $this->owner->createAsPaystackCustomer($options);
        } else {
            $customer = $this->owner->asPaystackCustomer();
        }
        return $customer;
    }

    /**
     * Create a new Paystack subscription using authorization code.
     *
     * @param string|null $authorization
     * @param $customer
     * @return \Digikraaft\PaystackSubscription\Subscription
     */
    protected function createSubscriptionFromAuthorization(string $authorization, $customer)
    {
        $payload = array_merge(
            ['customer' => $customer->data->customer_code],
            ['authorization' => $authorization],
            ['plan' => $this->plan],
        );

        return Subscription::create(
            $payload,
        )->data;
    }
}
