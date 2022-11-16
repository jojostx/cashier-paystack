<?php

namespace Jojostx\Cashier\Paystack;

use GuzzleHttp\Client;
use Illuminate\Support\Facades\Config;
use Unicodeveloper\Paystack\Exceptions\IsNullException;

class PaystackService
{
    /**
     * Issue Secret Key from your Paystack Dashboard
     * @var string
     */
    protected $secretKey;
    /**
     * Instance of Client
     * @var Client
     */
    protected $client;
    /**
     *  Response from requests made to Paystack
     * @var mixed
     */
    protected $response;
    /**
     * Paystack API base Url
     * @var string
     */

    public function __construct()
    {
        $this->setKey();
        $this->setBaseUrl();
        $this->setRequestOptions();
    }

    public static function make()
    {
        return new self;
    }

    /**
     * Get Base Url from Paystack config file
     */
    public function setBaseUrl()
    {
        $this->baseUrl = Config::get('paystack.paymentUrl');
    }

    /**
     * Get Base Url from Paystack config file
     */
    public function getBaseUrl()
    {
        return $this->baseUrl;
    }

    /**
     * Get secret key from Paystack config file
     */
    public function setKey()
    {
        $this->secretKey = Config::get('paystack.secretKey');
    }

    /**
     * Set options for making the Client request
     */
    private function setRequestOptions()
    {
        $authBearer = 'Bearer ' . $this->secretKey;
        $this->client = new Client(
            [
                'base_uri' => $this->baseUrl,
                'verify' => false,
                'headers' => [
                    'Authorization' => $authBearer,
                    'Content-Type'  => 'application/json',
                    'Accept'        => 'application/json'
                ]
            ]
        );
    }

    /**
     * @param string $relativeUrl
     * @param string $method
     * @param array $body
     * 
     * @throws IsNullException
     */
    private function setHttpResponse($relativeUrl, $method, $body = [])
    {
        if (is_null($method)) {
            throw new IsNullException("Empty method not allowed");
        }
        $this->response = $this->client->{strtolower($method)}(
            $this->baseUrl . $relativeUrl,
            ["body" => json_encode($body)]
        );
        return $this;
    }

    /**
     * Get the whole response from a get operation
     * @return array
     */
    private function getResponse()
    {
        return json_decode($this->response->getBody(), true);
    }

    /**
     * Get the data response from a get operation
     * @return array
     */
    private function getData()
    {
        return $this->getResponse()['data'];
    }


    /**
     * @param string $slug
     *
     * @return string the endpoint URL for the given class
     */
    public static function endPointUrl($base, $slug)
    {
        $slug = Helper::utf8($slug);

        return "{$base}/{$slug}";
    }

    /**
     * @param string $slug
     * @param $params array of query parameters
     *
     * @return string the endpoint URL for the given class
     */
    public static function buildQueryString($base, $slug, $params = null)
    {
        $url = self::endPointUrl($base, $slug);
        if (!empty($params)) {
            $url .= '?' . http_build_query($params);
        }

        return $url;
    }

    public static function charge($data)
    {
        return static::make()->setHttpResponse('/charge', 'POST', $data)->getResponse();
    }

    public static function chargeAuthorization($data)
    {
        return static::make()->setHttpResponse('/charge_authorization', 'POST', $data)->getResponse();
    }

    public static function checkAuthorization($data)
    {
        return static::make()->setHttpResponse('/check_authorization', 'POST', $data)->getResponse();
    }

    public static function deactivateAuthorization($auth_code)
    {
        $data = ['authorization_code' => $auth_code];
        return static::make()->setHttpResponse('/deactivate_authorization', 'POST', $data)->getResponse();
    }

    /**
     * @param array $params details at
     *
     * @link https://paystack.com/docs/api/#transaction-initialize
     *
     * @return array|object
     */
    public static function transactionInitialize($params)
    {
        return static::make()->setHttpResponse('/transaction/initialize', 'POST', $params)->getResponse();
    }

    /**
     * @param string $reference details at
     *
     * @link https://paystack.com/docs/api/#transaction-verify
     *
     * @return array|object
     */
    public static function transactionVerify($reference)
    {
        return static::make()->setHttpResponse("/transaction/verify/{$reference}", 'GET')->getData();
    }

    /**
     * @param string $transaction_id details at
     *
     * @link https://paystack.com/docs/api/#transaction-view-timeline
     *
     * @return array|object
     */
    public static function transactionTimeline($transaction_id)
    {
        return static::make()->setHttpResponse("/transaction/timeline/$transaction_id", 'GET')->getData();
    }

    /**
     * @param array $params details at
     *
     * @link https://paystack.com/docs/api/#transaction-totals
     *
     * @return array|object
     */
    public static function transactionTotals($params)
    {
        $url = static::buildQueryString('transaction', 'totals', $params);
        return static::make()->setHttpResponse($url, 'GET')->getData();
    }

    /**
     * @param array $params details at
     *
     * @link https://paystack.com/docs/api/#transaction-export
     *
     * @return array|object
     */
    public static function transactionExport($params)
    {
        $url = static::buildQueryString('transaction', 'export', $params);
        return static::make()->setHttpResponse($url, 'GET', $params)->getData();
    }

    /**
     * @param array $params details at
     *
     * @link https://paystack.com/docs/api/#transaction-partial-debit
     *
     * @return array|object
     */
    public static function transactionPartialDebit($params)
    {
        return static::make()->setHttpResponse("/transaction/partial_debit", 'POST', $params)->getResponse();
    }

    public static function refund($params)
    {
        return static::make()->setHttpResponse('/refund', 'POST', $params)->getResponse();
    }

    public static function createCustomer($params)
    {
        return static::make()->setHttpResponse('/customer', 'POST', $params)->getResponse();
    }

    /**
     * @param string $customer_id     Resource id
     * @param array  $params
     *
     * @return array|object
     */
    public static function updateCustomer(string $customer_id, $params)
    {
        return static::make()->setHttpResponse("/customer/{$customer_id}", 'PUT', $params)->getResponse();
    }

    public static function customerSubscriptions($customer_id)
    {
        $params = ['customer' => $customer_id];
        return static::make()->setHttpResponse('/subscription', 'GET', $params)->getData();
    }

    public static function createSubscription($params)
    {
        return static::make()->setHttpResponse('/subscription', 'POST', $params)->getResponse();
    }

    /**
     * Enable a subscription using the subscription code and token
     * @return array
     */
    public static function enableSubscription($data)
    {
        return static::make()->setHttpResponse('/subscription/enable', 'POST', $data)->getResponse();
    }
    /**
     * Disable a subscription using the subscription code and token
     * @return array
     */
    public static function disableSubscription($data)
    {
        return static::make()->setHttpResponse('/subscription/disable', 'POST', $data)->getResponse();
    }

    public static function createInvoice($data)
    {
        return static::make()->setHttpResponse('/paymentrequest', 'POST', $data)->getResponse();
    }

    public static function fetchInvoices($data)
    {
        return static::make()->setHttpResponse('/paymentrequest', 'GET', $data)->getData();
    }

    public static function findInvoice($invoice_id)
    {
        return static::make()->setHttpResponse("/paymentrequest/$invoice_id", 'GET', [])->getData();
    }

    public static function updateInvoice($invoice_id, $data)
    {
        return static::make()->setHttpResponse("/paymentrequest/$invoice_id", 'PUT', $data)->getResponse();
    }

    public static function verifyInvoice($invoice_code)
    {
        return static::make()->setHttpResponse('/paymentrequest/verify/' . $invoice_code, 'GET', [])->getData();
    }

    public static function notifyInvoice($invoice_id)
    {
        return static::make()->setHttpResponse('/paymentrequest/notify/' . $invoice_id, 'POST', [])->getResponse();
    }

    public static function finalizeInvoice($invoice_id)
    {
        return static::make()->setHttpResponse('/paymentrequest/finalize/' . $invoice_id, 'POST', [])->getResponse();
    }

    public static function archiveInvoice($invoice_id)
    {
        return static::make()->setHttpResponse('/paymentrequest/archive/' . $invoice_id, 'POST', [])->getResponse();
    }

    public static function createPlan($data)
    {
        return static::make()->setHttpResponse('/plan', 'POST', $data)->getData();
    }
}
