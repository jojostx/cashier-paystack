<?php

namespace Jojostx\Cashier\Paystack\Exceptions;

class CustomerCreationException extends \Exception
{

  /**
   * Create a new CustomerCreationException instance.
   *
   * @param  \Illuminate\Database\Eloquent\Model  $owner
   * @return static
   */
  public static function failed($message)
  {
    return new static('Unable to create Paystack customer: ' . $message);
  }
}
