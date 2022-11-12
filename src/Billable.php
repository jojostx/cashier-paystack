<?php

namespace Jojostx\Cashier\Paystack;

use Exception;
use Carbon\Carbon;
use InvalidArgumentException;
use Illuminate\Support\Collection;
use Jojostx\Cashier\Paystack\Concerns\ManagesCustomer;
use Jojostx\Cashier\Paystack\Concerns\ManagesSubscriptions;
use Unicodeveloper\Paystack\Facades\Paystack;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

trait Billable
{
    use ManagesCustomer;
    use ManagesSubscriptions;

    /**
     * Make a "one off" or "recurring" charge on the customer for the given amount or plan respectively
     *
     * @param  $amount
     * @param  array  $options
     * @throws \Exception
     */
    public function charge($amount, array $options = [])
    {
        $options = array_merge([
            'currency' => $this->preferredCurrency(),
            'reference' => Paystack::genTranxRef(),
        ], $options);

        $options['email'] = $this->email;
        $options['amount'] = intval($amount);
        if (array_key_exists('authorization_code', $options)) {
            $response = PaystackService::chargeAuthorization($options);
        } elseif (array_key_exists('card', $options) || array_key_exists('bank', $options)) {
            $response = PaystackService::charge($options);
        } else {
            $response = PaystackService::makePaymentRequest($options);
        }

        if (!$response['status']) {
            throw new Exception('Paystack was unable to perform a charge: ' . $response->message);
        }
        return $response;
    }

    /**
     * Refund a customer for a charge.
     *
     * @param  string  $charge
     * @param  array  $options
     * @return $response
     * @throws \InvalidArgumentException
     */
    public function refund($transaction, array $options = [])
    {
        $options['transaction'] = $transaction;

        $response = PaystackService::refund($options);
        return $response;
    }

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
            //
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
            throw new NotFoundHttpException;
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
    /**
     * Get a collection of the entity's payment methods.
     *
     * @param  array  $parameters
     * @return \Illuminate\Support\Collection
     */
    public function cards($parameters = [])
    {
        $cards = [];
        $paystackAuthorizations = $this->asPaystackCustomer()->authorizations;
        if (!is_null($paystackAuthorizations)) {
            foreach ($paystackAuthorizations as $card) {
                if ($card['channel'] == 'card')
                    $cards[] = new Card($this, $card);
            }
        }
        return new Collection($cards);
    }
    /**
     * Deletes the entity's payment methods.
     *
     * @return void
     */
    public function deleteCards()
    {
        $this->cards()->each(function ($card) {
            $card->delete();
        });
    }
  
    /**
     * Get the Paystack supported currency used by the entity.
     *
     * @return string
     */
    public function preferredCurrency()
    {
        return Cashier::usesCurrency();
    }
}
