<?php

namespace Jojostx\CashierPaystack\Concerns;

use Illuminate\Support\Carbon;
use Jojostx\CashierPaystack\Cashier;
use Jojostx\CashierPaystack\Models\Subscription;
use Jojostx\CashierPaystack\SubscriptionBuilder;

trait ManagesSubscription
{
  /**
   * Get all of the subscriptions for the Billable model.
   *
   * @return \Illuminate\Database\Eloquent\Relations\MorphMany
   */
  public function subscriptions()
  {
    return $this->morphMany(Cashier::subscriptionModel(), 'billable')->orderByDesc('created_at');
  }

  /**
   * Get a subscription instance by name.
   *
   * @param  string  $name
   * @return \Jojostx\CashierPaystack\Subscription|null
   */
  public function subscription($name = 'default')
  {
    return $this->subscriptions->where('name', $name)->first();
  }

  /**
   * Begin creating a new subscription.
   *
   * @param  string  $name
   * @param  string  $plan
   * @return \Jojostx\CashierPaystack\SubscriptionBuilder
   */
  public function newSubscription($name = 'default', $plan): SubscriptionBuilder
  {
    return new SubscriptionBuilder($this, $name, $plan);
  }

  /**
   * Determine if the model is on trial.
   *
   * @param  string  $name
   * @param  string|null  $plan
   * @return bool
   */
  public function onTrial($name = 'default', $plan = null)
  {
    if (func_num_args() === 0 && $this->onGenericTrial()) {
      return true;
    }
    $subscription = $this->subscription($name);
    if (is_null($plan)) {
      return $subscription && $subscription->onTrial();
    }
    return $subscription && $subscription->onTrial() &&
      $subscription->paystack_plan === $plan;
  }

  /**
   * Determine if the model is on a "generic" trial at the user level.
   *
   * @return bool
   */
  public function onGenericTrial()
  {
    return $this->trial_ends_at && Carbon::now()->lt($this->trial_ends_at);
  }

  /**
   * Determine if the Billable model's "generic" trial at the model level has expired.
   *
   * @return bool
   */
  public function hasExpiredGenericTrial()
  {
    if (is_null($this->customer)) {
      return false;
    }

    return $this->customer->hasExpiredGenericTrial();
  }

  /**
   * Get the ending date of the trial.
   *
   * @param  string  $name
   * @return \Illuminate\Support\Carbon|null
   */
  public function trialEndsAt($name = 'default')
  {
    if ($subscription = $this->subscription($name)) {
      return $subscription->trial_ends_at;
    }

    return $this->customer->trial_ends_at;
  }

  /**
   * Determine if the model has a given subscription.
   *
   * @param  string  $name
   * @param  string|null  $plan
   * @return bool
   */
  public function subscribed($name = 'default', $plan = null)
  {
    $subscription = $this->subscription($name);

    if (is_null($subscription)) {
      return false;
    }
    if (is_null($plan)) {
      return $subscription->valid();
    }
    return $subscription->valid() && $subscription->hasPlan($plan);
  }

  /**
   * Determine if the model is actively subscribed to one of the given plans.
   *
   * @param  array|string  $plans
   * @param  string  $name
   * @return bool
   */
  public function subscribedToPlan($plans, $name = 'default')
  {
    $subscription = $this->subscription($name);
    if (!$subscription || !$subscription->valid()) {
      return false;
    }
    foreach ((array) $plans as $plan) {
      return $subscription->hasPlan($plan);
    }
    return false;
  }

  /**
   * Determine if the entity is on the given plan.
   *
   * @param  string  $plan
   * @return bool
   */
  public function onPlan($plan)
  {
    return !is_null($this->subscriptions()
      ->where('paystack_plan', $plan)
      ->get()
      ->first(function (Subscription $subscription) {
        return $subscription->valid();
      }));
  }
}
