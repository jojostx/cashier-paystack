<?php

namespace Jojostx\CashierPaystack;

use GuzzleHttp\Exception\ClientException;

class Payment
{
    /**
     * Determine if the transaction is valid.
     *
     * @param  string  $transactionRef
     * @return bool
     */
    public static function hasValidTransaction(string $transactionRef)
    {
        try {
            $data = PaystackService::transactionVerify($transactionRef);
            return $data['status'] == 'success';
        } catch (ClientException $exception) {
            return false;
        }
    }
}