<?php

namespace Jojostx\Cashier\Paystack;

use Illuminate\Database\Eloquent\Model;

/**
 * @property \Jojostx\Cashier\Paystack\Billable $billable
 */
class Customer extends Model
{
    /**
     * The attributes that are not mass assignable.
     *
     * @var array
     */
    protected $guarded = [];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [
        'trial_ends_at' => 'datetime',
    ];

    /**
     * Get the billable model related to the customer.
     *
     * @return \Illuminate\Database\Eloquent\Relations\MorphTo
     */
    public function billable()
    {
        return $this->morphTo();
    }

    /**
     * Determine if the Paystack model is on a "generic" trial at the model level.
     *
     * @return bool
     */
    public function onGenericTrial()
    {
        return $this->trial_ends_at && $this->trial_ends_at->isFuture();
    }

    /**
     * Determine if the Paystack model has an expired "generic" trial at the model level.
     *
     * @return bool
     */
    public function hasExpiredGenericTrial()
    {
        return $this->trial_ends_at && $this->trial_ends_at->isPast();
    }
}