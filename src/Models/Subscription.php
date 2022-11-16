<?php

namespace Jojostx\CashierPaystack\Models;

use Carbon\Carbon;
use LogicException;
use Illuminate\Database\Eloquent\Model;
use Jojostx\CashierPaystack\Helper;
use Jojostx\CashierPaystack\PaystackService;

class Subscription extends Model
{
    const STATUS_ACTIVE = 'active';
    const STATUS_NONRENEWING = 'non-renewing';
    const STATUS_ATTENTION = 'attention';
    const STATUS_COMPLETED = 'completed';
    const STATUS_CANCELLED = 'cancelled';

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'paystack_subscriptions';

    /**
     * The attributes that are not mass assignable.
     *
     * @var array
     */
    protected $guarded = [];

    /**
     * The attributes that should be mutated to dates.
     *
     * @var array
     */
    protected $dates = [
        'trial_ends_at', 'ends_at',
        'created_at', 'updated_at',
    ];

    /**
     * Get the user that owns the subscription.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function user()
    {
        return $this->billable();
    }

    /**
     * Get the billable model related to the subscription.
     *
     * @return \Illuminate\Database\Eloquent\Relations\MorphTo
     */
    public function billable()
    {
        return $this->morphTo();
    }

    /**
     * Determine if the subscription is active, on trial, or within its grace period.
     *
     * @return bool
     */
    public function valid()
    {
        return $this->active() || $this->onTrial() || $this->onGracePeriod();
    }

    /**
     * Determine if the subscription is active.
     *
     * @return bool
     */
    public function active()
    {
        return is_null($this->ends_at) || $this->onGracePeriod() || $this->paystack_status === self::STATUS_ACTIVE;
    }

    /**
     * Filter query by active.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return void
     */
    public function scopeActive($query)
    {
        $query->where(function ($query) {
            $query->whereNull('ends_at')
                ->orWhere(function ($query) {
                    $query->onGracePeriod();
                })
                ->orWhere('paystack_status', self::STATUS_ACTIVE);
        });
    }

    /**
     * Filter query by cancel.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return void
     */
    public function scopeCancelled($query)
    {
        $query->where(function ($query) {
            $query->where('paystack_status', '==', self::STATUS_CANCELLED);
        });
    }

    /**
     * Filter query by on grace period.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return void
     */
    public function scopeOnGracePeriod($query)
    {
        $query->whereNotNull('ends_at')->where('ends_at', '>', Carbon::now());
    }

    /**
     * Filter query by not on grace period.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return void
     */
    public function scopeNotOnGracePeriod($query)
    {
        $query->whereNull('ends_at')->orWhere('ends_at', '<=', Carbon::now());
    }

    /**
     * Get the date when subscription ends
     * @return string
     */
    public function endsAt()
    {
        return $this->ends_at;
    }

    /**
     * Get the number of days left on current subscription
     * @return int
     */
    public function daysLeft()
    {
        $ends_at = Carbon::parse($this->endsAt());

        return $ends_at->diffInDays(Carbon::now());
    }

    /**
     * Determine if the subscription is recurring and not on trial.
     *
     * @return bool
     */
    public function recurring()
    {
        return !$this->onTrial() && !$this->cancelled();
    }

    /**
     * Determine if the subscription is no longer active.
     *
     * @return bool
     */
    public function cancelled()
    {
        return !is_null($this->ends_at) || $this->paystack_status === self::STATUS_CANCELLED;
    }

    /**
     * Determine if the subscription has ended and the grace period has expired.
     *
     * @return bool
     */
    public function ended()
    {
        return $this->cancelled() && !$this->onGracePeriod();
    }

    /**
     * Determine if the subscription is within its trial period.
     *
     * @return bool
     */
    public function onTrial()
    {
        return $this->trial_ends_at && $this->trial_ends_at->isFuture();
    }

    /**
     * Determine if the subscription is within its grace period after cancellation.
     *
     * @return bool
     */
    public function onGracePeriod()
    {
        return $this->ends_at && $this->ends_at->isFuture();
    }

    /**
     * Force the trial to end immediately.
     *
     * This method must be combined with swap, resume, etc.
     *
     * @return $this
     */
    public function skipTrial()
    {
        $this->trial_ends_at = null;
        return $this;
    }

    /**
     * Cancel the subscription at the end of the billing period.
     *
     * @return $this
     */
    public function cancel()
    {
        $subscription = $this->asPaystackSubscription();

        PaystackService::disableSubscription([
            'token' => $subscription->email_token,
            'code'  => $subscription->subscription_code,
        ]);
        // If the user was on trial, we will set the grace period to end when the trial
        // would have ended. Otherwise, we'll retrieve the end of the billing period
        // period and make that the end of the grace period for this current user.
        if ($this->onTrial()) {
            $this->ends_at = $this->trial_ends_at;
        } else {
            $this->ends_at = Carbon::parse(
                $subscription->next_payment_date
            );
        }
        $this->save();
        return $this;
    }

    /**
     * Cancel the subscription immediately.
     *
     * @return $this
     */
    public function cancelNow()
    {
        $this->cancel();
        $this->markAsCancelled();
        return $this;
    }

    /**
     * Resume the cancelled subscription.
     *
     * @return $this
     * @throws \LogicException
     */
    public function resume()
    {
        $subscription = $this->asPaystackSubscription();
        // To resume the subscription we need to enable the Paystack
        // subscription. Then Paystack will resume this subscription
        // where we left off.
        PaystackService::enableSubscription([
            'token' => $subscription->email_token,
            'code'  => $subscription->subscription_code,
        ]);
        // Finally, we will remove the ending timestamp from the user's record in the
        // local database to indicate that the subscription is active again and is
        // no longer "cancelled". Then we will save this record in the database.
        $this->fill(['ends_at' => null])->save();
        return $this;
    }

    /**
     * Get the subscription as a Paystack subscription object.
     *
     * @throws \LogicException
     */
    public function asPaystackSubscription()
    {
        $subscriptions = PaystackService::customerSubscriptions($this->user->paystack_id);

        if (!$subscriptions || empty($subscriptions)) {
            throw new LogicException('The Paystack customer does not have any subscriptions.');
        }
        foreach ($subscriptions as $subscription) {
            if ($subscription['id'] == $this->paystack_id) {
                return Helper::convertArrayToObject($subscription);
            }
        }

        throw new LogicException('The Paystack subscription does not exist for this customer.');
    }

    /**
     * Determine if the subscription has a specific plan.
     *
     * @param  int  $plan
     * @return bool
     */
    public function hasPlan($plan)
    {
        return $this->paystack_plan == $plan;
    }

    /**
     * Perform a "one off" charge on top of the subscription for the given amount.
     *
     * @param  array $attributes
     * @return mixed
     *
     * @throws \Exception
     */
    public function charge($attributes)
    {
        if (!\array_key_exists('authorization_code', $attributes)) {
            throw new \Exception('Missing required attribute: ["authorization_code"].');
        }

        $payload = \array_intersect_key($attributes, [
            'amount',
            'email',
            'authorization_code',
            'reference',
            'currency',
            'metadata',
            'channels',
            'subaccount',
            'transaction_charge',
            'bearer',
            'queue',
        ]);

        return PaystackService::chargeAuthorization($payload);
    }

    /**
     * Swap the subscription to a new Paystack plan.
     * 
     * @param  int $plan
     * @return $this
     * @throws \LogicException
     */
    public function swap($plan, array $options = [])
    {
        $this->guardAgainstUpdates('swap plans');

        $this->updatePaystackSubscription(array_merge($options, ['plan_id' => $plan]));

        return $this;
    }

    /**
     * Sync the Paystack status of the subscription.
     *
     * @return void
     */
    public function syncPaystackStatus()
    {
        $subscription = $this->asPaystackSubscription();
        $this->paystack_status = $subscription->status;

        if ($subscription->next_payment_date != null) {
            $this->ends_at = $subscription->next_payment_date;
        }
        $this->save();
    }

    /**
     * Mark the subscription as cancelled.
     *
     * @return void
     */
    public function markAsCancelled()
    {
        $this->fill(['ends_at' => Carbon::now()])->save();
    }

    /**
     * Perform a guard check to prevent change for a specific action.
     *
     * @param  string  $action
     * @return void
     *
     * @throws \LogicException
     */
    public function guardAgainstUpdates($action): void
    {
        if ($this->onTrial()) {
            throw new LogicException("Cannot $action while on trial.");
        }

        if ($this->cancelled() || $this->onGracePeriod()) {
            throw new LogicException("Cannot $action for cancelled subscriptions.");
        }

        if ($this->ended()) {
            throw new LogicException("Cannot $action for past due subscriptions.");
        }
    }
}
