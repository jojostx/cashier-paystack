<?php

namespace Jojostx\Cashier\Paystack\Concerns;

use Jojostx\Cashier\Paystack\Cashier;
use Jojostx\Cashier\Paystack\PaystackService;
use Unicodeveloper\Paystack\Facades\Paystack;

trait ManagesCustomer
{
  /**
   * Get the customer related to the billable model.
   *
   * @return \Illuminate\Database\Eloquent\Relations\MorphOne
   */
  public function customer()
  {
    return $this->morphOne(Cashier::paystackModel(), 'billable');
  }

  /**
   * Create a customer for the given model.
   */
  public function createAsCustomer(array $attributes = [])
  {
    return $this->customer()->create($attributes);
  }

  /**
   * Create a Paystack customer for the given model.
   *
   * @param  array  $attributes
   * @throws \Exception
   */
  public function createAsPaystackCustomer(array $attributes = [])
  {
    $attributes = array_key_exists('email', $attributes)
      ? $attributes
      : array_merge($attributes, ['email' => $this->customer]);

    $response = PaystackService::createCustomer($attributes);

    if (!$response['status']) {
      throw new \Exception('Unable to create Paystack customer: ' . $response['message']);
    }

    $this->customer->paystack_id = $response['data']['id'];
    $this->customer->paystack_code = $response['data']['customer_code'];
    $this->customer->save();

    return $response['data'];
  }

  /**
   * Get the Paystack customer for the model.
   *
   * @return $customer
   */
  public function asPaystackCustomer()
  {
    return Paystack::fetchCustomer($this->customer->paystack_id)['data'];
  }

  /**
   * Determine if the entity has a Paystack customer ID.
   *
   * @return bool
   */
  public function hasPaystackId()
  {
    return !is_null($this->customer->paystack_id);
  }
}
