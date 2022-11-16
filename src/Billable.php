<?php

namespace Jojostx\CashierPaystack;

use Exception;
use InvalidArgumentException;
use Illuminate\Support\Collection;
use Jojostx\CashierPaystack\Concerns\ManagesCustomer;
use Jojostx\CashierPaystack\Concerns\ManagesInvoice;
use Jojostx\CashierPaystack\Concerns\ManagesSubscription;
use Unicodeveloper\Paystack\Facades\Paystack;

trait Billable
{
    use ManagesCustomer;
    use ManagesSubscription;
    use ManagesInvoice;

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
            $response = PaystackService::transactionInitialize($options);
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
