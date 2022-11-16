<?php

namespace Tests\Fixtures;

use Illuminate\Foundation\Auth\User as Model;
use Illuminate\Notifications\Notifiable;
use Jojostx\Cashier\Paystack\Billable;

class User extends Model
{
    use Billable, Notifiable;

    protected $guarded = [];
}
