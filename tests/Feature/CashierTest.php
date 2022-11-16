<?php

namespace Tests\Feature;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Jojostx\CashierPaystack\PaystackService;
use Illuminate\Support\Str;
use Jojostx\CashierPaystack\Http\Controllers\WebhookController;
use Tests\Fixtures\User;

class CashierTest extends FeatureTestCase
{
    public function test_charging_on_user()
    {
        $user = User::create([
            'email' => 'wisdomanthoni@gmail.com',
            'name' => 'Wisdom Anthony',
        ]);
        $charge = $user->charge(500000);
        $this->assertTrue($charge['status']);
        $this->assertEquals('Authorization URL created', $charge['message']);
    }
    public function test_subscriptions_can_be_created()
    {
        $user = User::create([
            'email' => 'wisdomanthoni@gmail.com',
            'name' => 'Wisdom Anthony',
        ]);
        $this->runTestCharge($user);
        $plan_code = $this->getTestPlan()['plan_code'];
        // Create Subscription
        $user->newSubscription('main', $plan_code)->create();

        $this->assertEquals(1, count($user->subscriptions));
        $this->assertNotNull($user->subscription('main')->paystack_id);
        $this->assertTrue($user->subscribed('main'));
        $this->assertTrue($user->subscribedToPlan($plan_code, 'main'));
        $this->assertFalse($user->subscribedToPlan('PLN_cgumntiwkkda3cw', 'something'));
        $this->assertFalse($user->subscribedToPlan('PLN_cgumntiwkkda3cw', 'main'));
        $this->assertTrue($user->subscribed('main', $plan_code));
        $this->assertFalse($user->subscribed('main', 'PLN_cgumntiwkkda3cw'));
        $this->assertTrue($user->subscription('main')->active());
        $this->assertFalse($user->subscription('main')->cancelled());
        $this->assertFalse($user->subscription('main')->onGracePeriod());
        $this->assertTrue($user->subscription('main')->recurring());
        $this->assertFalse($user->subscription('main')->ended());

        $subscription = $user->subscription('main');

        // Cancel Subscription
        $subscription->cancel();
        $this->assertFalse($subscription->active());
        $this->assertTrue($subscription->cancelled());
        $this->assertFalse($subscription->onGracePeriod());
        $this->assertFalse($subscription->recurring());
        $this->assertTrue($subscription->ended());

        // Modify Ends Date To Past
        $subscription->fill(['ends_at' => Carbon::now()->subDays(5)])->save();
        $this->assertFalse($subscription->active());
        $this->assertTrue($subscription->cancelled());
        $this->assertFalse($subscription->onGracePeriod());
        $this->assertFalse($subscription->recurring());
        $this->assertTrue($subscription->ended());

        // Resume Subscription
        $subscription->resume();
        $this->assertTrue($subscription->active());
        $this->assertFalse($subscription->cancelled());
        $this->assertFalse($subscription->onGracePeriod());
        $this->assertTrue($subscription->recurring());
        $this->assertFalse($subscription->ended());
    }
    public function test_creating_subscription_from_webhook()
    {
        $user = User::create([
            'email' => 'wisdomanthoni@gmail.com',
            'name' => 'Wisdom Anthony',
        ]);

        $request = Request::create('/', 'POST', [], [], [], [], json_encode(array(
            'event' => 'subscription.create',
            'data' =>
            array(
                'domain' => 'test',
                'status' => 'complete',
                'subscription_code' => 'SUB_vsyqdmlzble3uii',
                'amount' => 50000,
                'cron_expression' => '0 0 28 * *',
                'next_payment_date' => '2016-05-19T07:00:00.000Z',
                'open_invoice' => NULL,
                'createdAt' => '2016-03-20T00:23:24.000Z',
                'plan' =>
                array(
                    'name' => 'Monthly retainer',
                    'plan_code' => 'PLN_gx2wn530m0i3w3m',
                    'description' => NULL,
                    'amount' => 50000,
                    'interval' => 'monthly',
                    'send_invoices' => true,
                    'send_sms' => true,
                    'currency' => 'NGN',
                ),
                'authorization' =>
                array(
                    'authorization_code' => 'AUTH_96xphygz',
                    'bin' => '539983',
                    'last4' => '7357',
                    'exp_month' => '10',
                    'exp_year' => '2017',
                    'card_type' => 'MASTERCARD DEBIT',
                    'bank' => 'GTBANK',
                    'country_code' => 'NG',
                    'brand' => 'MASTERCARD',
                ),
                'customer' =>
                array(
                    'first_name' => 'BoJack',
                    'last_name' => 'Horseman',
                    'email' => 'bojack@horsinaround.com',
                    'customer_code' => $user->paystack_code,
                    'phone' => '',
                    array(),
                    'risk_action' => 'default',
                ),
                'created_at' => '2016-10-01T10:59:59.000Z',
            ),
        )));

        $controller = new CashierTestControllerStub;
        $response = $controller->handleWebhook($request);
        $this->assertEquals(200, $response->getStatusCode());

        $user = $user->fresh();

        $subscription = $user->subscription('Monthly retainer');

        $this->assertTrue($subscription->active());
        $this->assertFalse($subscription->cancelled());
        $this->assertFalse($subscription->onGracePeriod());
        $this->assertTrue($subscription->recurring());
        $this->assertFalse($subscription->ended());
    }
    public function test_generic_trials()
    {
        $user = new User;
        $this->assertFalse($user->onGenericTrial());
        $user->trial_ends_at = Carbon::tomorrow();
        $this->assertTrue($user->onGenericTrial());
        $user->trial_ends_at = Carbon::today()->subDays(5);
        $this->assertFalse($user->onGenericTrial());
    }
    public function test_creating_subscription_with_trial()
    {
        $user = User::create([
            'email' => 'wisdomanthoni@gmail.com',
            'name' => 'Wisdom Anthony',
        ]);
        $plan_code = $this->getTestPlan()['plan_code'];
        // Create Subscription
        $user->newSubscription('main', $plan_code)
            ->trialDays(7)
            ->create();
        $subscription = $user->subscription('main');
        $this->assertTrue($subscription->active());
        $this->assertTrue($subscription->onTrial());
        $this->assertFalse($subscription->recurring());
        $this->assertFalse($subscription->ended());
        $this->assertEquals(Carbon::today()->addDays(7)->day, $subscription->trial_ends_at->day);
        // Cancel Subscription
        $subscription->cancel();
        $this->assertTrue($subscription->active());
        $this->assertTrue($subscription->onGracePeriod());
        $this->assertFalse($subscription->recurring());
        $this->assertFalse($subscription->ended());
        // Resume Subscription
        $subscription->resume();
        $this->assertTrue($subscription->active());
        $this->assertFalse($subscription->onGracePeriod());
        $this->assertTrue($subscription->onTrial());
        $this->assertFalse($subscription->recurring());
        $this->assertFalse($subscription->ended());
        $this->assertEquals(Carbon::today()->addDays(7)->day, $subscription->trial_ends_at->day);
    }
    public function test_marking_as_cancelled_from_webhook()
    {
        $user = User::create([
            'email' => 'wisdomanthoni@gmail.com',
            'name' => 'Wisdom Anthony',
        ]);
        $plan_code = $this->getTestPlan()['plan_code'];

        // Create Subscription
        $user->newSubscription('main', $plan_code)->create();
        // Fetch Subscription
        $subscription = $user->subscription('main');

        $request = Request::create('/', 'POST', [], [], [], [], json_encode(array(
            'event' => 'subscription.disable',
            'data' =>
            array(
                'domain' => 'test',
                'status' => 'complete',
                'subscription_code' => $subscription->paystack_code,
                'amount' => 50000,
                'cron_expression' => '0 0 28 * *',
                'next_payment_date' => '2016-05-19T07:00:00.000Z',
                'open_invoice' => NULL,
                'createdAt' => '2016-03-20T00:23:24.000Z',
                'plan' =>
                array(
                    'name' => 'Monthly retainer',
                    'plan_code' => $subscription->paystack_plan,
                    'description' => NULL,
                    'amount' => 50000,
                    'interval' => 'monthly',
                    'send_invoices' => true,
                    'send_sms' => true,
                    'currency' => 'NGN',
                ),
                'authorization' =>
                array(
                    'authorization_code' => 'AUTH_96xphygz',
                    'bin' => '539983',
                    'last4' => '7357',
                    'exp_month' => '10',
                    'exp_year' => '2017',
                    'card_type' => 'MASTERCARD DEBIT',
                    'bank' => 'GTBANK',
                    'country_code' => 'NG',
                    'brand' => 'MASTERCARD',
                ),
                'customer' =>
                array(
                    'first_name' => 'BoJack',
                    'last_name' => 'Horseman',
                    'email' => 'bojack@horsinaround.com',
                    'customer_code' => $user->paystack_code,
                    'phone' => '',
                    array(),
                    'risk_action' => 'default',
                ),
                'created_at' => '2016-10-01T10:59:59.000Z',
            ),
        )));
        $controller = new CashierTestControllerStub;
        $response = $controller->handleWebhook($request);
        $this->assertEquals(200, $response->getStatusCode());
        $user = $user->fresh();
        $subscription = $user->subscription('main');
        $this->assertTrue($subscription->cancelled());
    }
    public function test_creating_one_off_invoices()
    {
        $user = User::create([
            'email' => 'wisdomanthoni@gmail.com',
            'name' => 'Wisdom Anthony',
        ]);
        $user->createAsPaystackCustomer();
        // Create Invoice
        $options['due_date'] = 'Next Week';
        $user->invoiceFor('Paystack Cashier', 100000, $options);
        // Invoice Tests
        $invoice = $user->invoices()[0];
        $this->assertEquals('₦1,000.00', $invoice->total());
        $this->assertEquals('Paystack Cashier', $invoice->description);
    }
    protected function runTestCharge($user)
    {
        return $user->charge(10000, ['card' => $this->getTestCard()]);
    }

    protected function getTestPlan()
    {
        $data = [
            "name" => 'Plan ' . Str::random(4),
            "desc" => 'A Plan to Test Subscription',
            "amount" => rand(50000, 100000),
            "interval" => 'monthly',
            "send_invoices" => false,
        ];
        return PaystackService::createPlan($data);
    }

    protected function getTestCard()
    {
        return [
            'number' => '408 408 408 408 408 1',
            'expiry_month' => 5,
            'expiry_year' => 2020,
            'cvv' => '408',
        ];
    }
}

class CashierTestControllerStub extends WebhookController
{
    public function __construct()
    {
        // Prevent setting middleware...
    }
}
