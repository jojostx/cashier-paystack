<?php

namespace Jojostx\CashierPaystack\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Str;
use Jojostx\CashierPaystack\Cashier;
use Jojostx\CashierPaystack\Events\SubscriptionCancelled;
use Jojostx\CashierPaystack\Events\SubscriptionCreated;
use Jojostx\CashierPaystack\Events\SubscriptionEnabled;
use Jojostx\CashierPaystack\Events\WebhookHandled;
use Jojostx\CashierPaystack\Events\WebhookReceived;
use Symfony\Component\HttpFoundation\Response;
use Jojostx\CashierPaystack\Http\Middleware\VerifyWebhookSignature;
use Jojostx\CashierPaystack\Models\Subscription;

class WebhookController extends Controller
{
    /**
     * Create a new webhook controller instance.
     *
     * @return voCode
     */
    public function __construct()
    {
        if (config('paystack.secretKey')) {
            $this->middleware(VerifyWebhookSignature::class);
        }
    }
    /**
     * Handle a Paystack webhook call.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function __invoke(Request $request)
    {
        $payload = json_decode($request->getContent(), true);

        if (!isset($payload['event'])) {
            return new Response();
        }

        WebhookReceived::dispatch($payload);

        $method = 'handle' . Str::studly(str_replace('.', '_', $payload['event']));

        if (method_exists($this, $method)) {
            return $this->{$method}($payload);
        }

        WebhookHandled::dispatch($payload);

        return $this->missingMethod($payload);
    }

    /**
     * Handle customer subscription create.
     *
     * @param  array $payload
     * @return \Symfony\Component\HttpFoundation\Response
     */
    protected function handleSubscriptionCreate(array $payload)
    {
        $data = $payload['data'];
        $user = $this->getUserByPaystackCode($data['customer']['customer_code']);
        $subscription = $this->getSubscriptionByCode($data['subscription_code']);

        if ($user && !isset($subscription)) {
            $plan = $data['plan'];
            $subscription = $user->newSubscription($plan['name'], $plan['plan_code']);
            $data['id'] =  null;
            $subscription = $subscription->add($data);
        }

        SubscriptionCreated::dispatch($user, $subscription, $payload);

        return $this->successMethod();
    }

    /**
     * Handle enabled customer subscription.
     *
     * @param  array  $payload
     * @return \Symfony\Component\HttpFoundation\Response
     */
    protected function handleSubscriptionEnable(array $payload)
    {
        $data = $payload['data'];

        if ($user = $this->getUserByPaystackCode($data['customer']['customer_code'])) {
            $user->subscriptions->filter(function ($subscription) use ($data) {
                return $subscription->paystack_id === $data['subscription_code'];
            })->each(function ($subscription) use ($data, $user, $payload) {
                if (isset($data['status'])) {
                    $subscription->paystack_status = $data['status'];
                    $subscription->ends_at = $data['next_payment_date'];

                    $subscription->save();

                    SubscriptionEnabled::dispatch($user, $subscription, $payload);

                    return $this->successMethod();
                }
            });
        }

        return $this->successMethod();
    }

    /**
     * Handle a subscription disabled notification from paystack.
     *
     * @param  array $payload
     * @return \Symfony\Component\HttpFoundation\Response
     */
    protected function handleSubscriptionDisable($payload)
    {
        return $this->cancelSubscription($payload['data']['subscription_code'], $payload);
    }

    /**
     * Handle a failed Invoice from a Paystack subscription.
     *
     * @param  array  $payload
     * @return \Symfony\Component\HttpFoundation\Response
     */
    protected function handleInvoiceFailed(array $payload)
    {
        $data = $payload['data'];

        if ($user = $this->getUserByPaystackCode($data['customer']['customer_code'])) {
            $user->subscriptions->filter(function ($subscription) use ($data) {
                return $subscription->paystack_id === $data['subscription_code'];
            })->each(function ($subscription) use ($data, $user, $payload) {
                if (isset($data['status'])) {
                    $subscription->paystack_status = $data['status'];
                    $subscription->ends_at = $data['next_payment_date'];

                    $subscription->save();

                    SubscriptionEnabled::dispatch($user, $subscription, $payload);

                    return $this->successMethod();
                }
            });
        }

        return $this->successMethod();
    }

    /**
     * Handle a subscription cancellation notification from paystack.
     *
     * @param  string  $subscriptionCode
     * @return \Symfony\Component\HttpFoundation\Response
     */
    protected function cancelSubscription($subscriptionCode, $payload)
    {
        $subscription = $this->getSubscriptionByCode($subscriptionCode);
        if ($subscription && (!$subscription->cancelled() || $subscription->onGracePeriod())) {
            $subscription->markAsCancelled();

            SubscriptionCancelled::dispatch($subscription, $payload);
        }
        return $this->successMethod();
    }

    /**
     * Get the model for the given subscription Code.
     *
     * @param  string  $subscriptionCode
     * @return \Jojostx\CashierPaystack\Subscription|null
     */
    protected function getSubscriptionByCode($subscriptionCode): ?Subscription
    {
        return Subscription::where('paystack_code', $subscriptionCode)->first();
    }
    /**
     * Get the billable entity instance by Paystack Code.
     *
     * @param  string  $paystackCode
     * @return \Jojostx\CashierPaystack\Billable
     */
    protected function getUserByPaystackCode($paystackCode)
    {
        $model = Cashier::paystackModel();
        return (new $model)->findBillable($paystackCode);
    }

    /**
     * Handle successful calls on the controller.
     *
     * @param  array  $parameters
     * @return \Symfony\Component\HttpFoundation\Response
     */
    protected function successMethod($parameters = [])
    {
        return new Response('Webhook Handled', 200);
    }

    /**
     * Handle calls to missing methods on the controller.
     *
     * @param  array  $parameters
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function missingMethod($parameters = [])
    {
        return new Response;
    }
}
