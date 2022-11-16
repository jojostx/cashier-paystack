<?php

namespace Jojostx\CashierPaystack\Concerns;

use Jojostx\CashierPaystack\Cashier;
use Jojostx\CashierPaystack\Exceptions\CustomerAlreadyExist;
use Jojostx\CashierPaystack\Exceptions\CustomerCreationException;
use Jojostx\CashierPaystack\Exceptions\InvalidCustomer;
use Jojostx\CashierPaystack\PaystackService;
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
    if ($this->hasPaystackId()) {
      throw CustomerAlreadyExist::exists($this->customer);
    }

    if (!array_key_exists('email', $attributes) && $email = $this->paystackEmail()) {
      $attributes['email'] = $email;
    }

    $response = PaystackService::createCustomer($attributes);

    if (!$response['status']) {
      throw CustomerCreationException::failed($response['message']);
    }

    $this->customer->paystack_id = $response['data']['id'];
    $this->customer->paystack_code = $response['data']['customer_code'];
    $this->customer->save();

    return $response['data'];
  }

  /**
   * Get the Paystack customer instance for the current user or create one.
   *
   * @param array $options
   * @return \Digikraaft\Paystack\Customer
   * @throws \Digikraaft\PaystackSubscription\Exceptions\InvalidCustomer
   */
  public function createOrGetPaystackCustomer(array $options = [])
  {
    if ($this->hasPaystackId()) {
      return $this->asPaystackCustomer();
    }

    return $this->createAsPaystackCustomer($options);
  }

  /**
   * Update the Paystack customer information for the model.
   *
   * @param  array  $params
   * @return \Digikraaft\Paystack\Customer
   */
  public function updatePaystackCustomer(array $params = [])
  {
    return PaystackService::updateCustomer(
      $this->customer->paystack_id,
      $params,
    );
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
   * Get the email address used to create the customer in Paystack.
   *
   * @return string|null
   */
  public function paystackEmail()
  {
    return $this->customer->email;
  }

  /**
   * Retrieve the Paystack customer ID.
   *
   * @return string|null
   */
  public function paystackId()
  {
    return $this->customer->paystack_id;
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

  /**
   * Determine if the entity has a Paystack authorization.
   *
   * @return bool
   */
  public function hasPaystackAuthorization()
  {
    return !is_null($this->customer->paystack_authorization);
  }

  /**
   * Get Paystack authorization.
   *
   * @return string
   */
  public function paystackAuthorization()
  {
    return $this->customer->paystack_authorization;
  }

  /**
   * Determine if the entity has a Paystack customer ID and throw an exception if not.
   *
   * @return void
   *
   * @throws \Digikraaft\PaystackSubscription\Exceptions\InvalidCustomer
   */
  protected function assertCustomerExists()
  {
    if (!$this->hasPaystackId()) {
      throw InvalidCustomer::doesNotExist($this);
    }
  }
}
