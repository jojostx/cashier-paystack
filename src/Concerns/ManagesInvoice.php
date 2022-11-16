<?php

namespace Jojostx\Cashier\Paystack\Concerns;

use Exception;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use InvalidArgumentException;
use Jojostx\Cashier\Paystack\Invoice;
use Jojostx\Cashier\Paystack\PaystackService;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

trait ManagesInvoice
{
  /**
   * Invoice the customer for the given amount.
   *
   * @param  string  $description
   * @param  int  $amount
   * @param  array  $options
   * @throws \Exception
   */
  public function tab($description, $amount, array $options = [])
  {
    if (!$this->paystack_id) {
      throw new InvalidArgumentException(class_basename($this) . ' is not a Paystack customer. See the createAsPaystackCustomer method.');
    }

    if (!array_key_exists('due_date', $options)) {
      throw new InvalidArgumentException('No due date provided.');
    }

    $options = array_merge([
      'customer' => $this->paystack_id,
      'amount' => $amount,
      'currency' => $this->preferredCurrency(),
      'description' => $description,
    ], $options);

    $options['due_date'] = Carbon::parse($options['due_date'])->format('c');

    return PaystackService::createInvoice($options);
  }

  /**
   * Invoice the billable entity outside of regular billing cycle.
   *
   * @param  string  $description
   * @param  int  $amount
   * @param  array  $options
   * @throws \Exception
   */
  public function invoiceFor($description, $amount, array $options = [])
  {
    return $this->tab($description, $amount, $options);
  }

  /**
   * Find an invoice by ID.
   *
   * @param  string  $id
   * @return \Jojostx\Cashier\Paystack\Invoice|null
   */
  public function findInvoice($id)
  {
    try {
      $invoice = PaystackService::findInvoice($id);
      if ($invoice['customer']['id'] != $this->paystack_id) {
        return;
      }
      return new Invoice($this, $invoice);
    } catch (Exception $e) {
      return null;
    }
  }
  /**
   * Find an invoice or throw a 404 error.
   *
   * @param  string  $id
   * @return \Jojostx\Cashier\Paystack\Invoice
   */
  public function findInvoiceOrFail($id): Invoice
  {
    $invoice = $this->findInvoice($id);
    if (is_null($invoice)) {
      throw new NotFoundHttpException('Unable to find invoice with id: ' . $id);
    }
    return $invoice;
  }
  /**
   * Create an invoice download Response.
   *
   * @param  string  $id
   * @param  array  $data
   * @return \Symfony\Component\HttpFoundation\Response
   * @throws \Throwable
   */
  public function downloadInvoice($id, array $data)
  {
    return $this->findInvoiceOrFail($id)->download($data);
  }
  /**
   * Get a collection of the entity's invoices.
   *
   * @param  bool  $includePending
   * @param  array  $parameters
   * @return \Illuminate\Support\Collection
   * @throws \Exception
   */
  public function invoices($options = []): Collection
  {
    if (!$this->hasPaystackId()) {
      throw new InvalidArgumentException(class_basename($this) . ' is not a Paystack customer. See the createAsPaystackCustomer method.');
    }

    $invoices = [];
    $parameters = array_merge(['customer' => $this->paystack_id], $options);
    $paystackInvoices = PaystackService::fetchInvoices($parameters);
    // Here we will loop through the Paystack invoices and create our own custom Invoice
    // instances that have more helper methods and are generally more convenient to
    // work with than the plain Paystack objects are. Then, we'll return the array.
    if (!is_null($paystackInvoices && !empty($paystackInvoices))) {
      foreach ($paystackInvoices as $invoice) {
        $invoices[] = new Invoice($this, $invoice);
      }
    }
    return new Collection($invoices);
  }

  /**
   * Get an array of the entity's invoices.
   *
   * @param  array  $parameters
   * @return \Illuminate\Support\Collection
   */
  public function invoicesOnlyPending(array $parameters = []): Collection
  {
    $parameters['status'] = 'pending';
    return $this->invoices($parameters);
  }
  /**
   * Get an array of the entity's invoices.
   *
   * @param  array  $parameters
   * @return \Illuminate\Support\Collection
   */
  public function invoicesOnlyPaid(array $parameters = []): Collection
  {
    $parameters['paid'] = true;
    return $this->invoices($parameters);
  }
}
