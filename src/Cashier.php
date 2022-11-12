<?php

namespace Jojostx\Cashier\Paystack;

use Exception;

class Cashier
{
    /**
     * Indicates if Cashier migrations will be run.
     *
     * @var bool
     */
    public static $runsMigrations = true;

    /**
     * Indicates if Cashier routes will be registered.
     *
     * @var bool
     */
    public static $registersRoutes = true;

    /**
     * The customer model class name.
     *
     * @var string
     */
    public static $customerModel = Customer::class;

    /**
     * The subscription model class name.
     *
     * @var string
     */
    public static $subscriptionModel = Subscription::class;

    /**
     * The current currency.
     *
     * @var string
     */
    protected static $currency = 'ngn';
    /**
     * The current currency symbol.
     *
     * @var string
     */
    protected static $currencySymbol = '₦';
    /**
     * The custom currency formatter.
     *
     * @var callable
     */
    protected static $formatCurrencyUsing;

    /**
     * Get the class name of the billable model.
     *
     * @return string
     */
    public static function paystackModel()
    {
        return static::$customerModel;
    }

    /**
     * Get the class name of the billable model.
     *
     * @return string
     */
    public static function subscriptionModel()
    {
        return static::$subscriptionModel;
    }

    /**
     * Set the currency to be used when billing models.
     *
     * @param  string  $currency
     * @param  string|null  $symbol
     * @return void
     * @throws \Exception
     */
    public static function useCurrency($currency, $symbol = null)
    {
        $currency = strtolower($currency);
        static::$currency = $currency;
        static::useCurrencySymbol($symbol ?: static::guessCurrencySymbol($currency));
    }
    /**
     * Guess the currency symbol for the given currency.
     *
     * @param  string  $currency
     * @return string
     * @throws \Exception
     */
    protected static function guessCurrencySymbol($currency)
    {
        switch (strtolower($currency)) {
            case 'ngn':
                return '₦';
            case 'ghs':
                return 'GH₵';
            case 'eur':
                return '€';
            case 'gbp':
                return '£';
            case 'usd':
            case 'aud':
            case 'cad':
                return '$';
            default:
                throw new Exception("Unable to guess symbol for currency. Please explicitly specify it.");
        }
    }
    /**
     * Get the currency currently in use.
     *
     * @return string
     */
    public static function usesCurrency()
    {
        return strtoupper(static::$currency);
    }
    /**
     * Set the currency symbol to be used when formatting currency.
     *
     * @param  string  $symbol
     * @return void
     */
    public static function useCurrencySymbol($symbol)
    {
        static::$currencySymbol = $symbol;
    }
    /**
     * Get the currency symbol currently in use.
     *
     * @return string
     */
    public static function usesCurrencySymbol()
    {
        return static::$currencySymbol;
    }
    /**
     * Set the custom currency formatter.
     *
     * @param  callable  $callback
     * @return void
     */
    public static function formatCurrencyUsing(callable $callback)
    {
        static::$formatCurrencyUsing = $callback;
    }
    /**
     * Format the given amount into a displayable currency.
     *
     * @param  int  $amount
     * @return string
     */
    public static function formatAmount($amount)
    {
        if (static::$formatCurrencyUsing) {
            return call_user_func(static::$formatCurrencyUsing, $amount);
        }
        $amount = number_format($amount / 100, 2);
        if (str_starts_with($amount, '-')) {
            return '-' . static::usesCurrencySymbol() . ltrim($amount, '-');
        }
        return static::usesCurrencySymbol() . $amount;
    }

    /**
     * Set the customer model class name.
     *
     * @param  string  $customerModel
     * @return void
     */
    public static function useCustomerModel($customerModel)
    {
        static::$customerModel = $customerModel;
    }

    /**
     * Set the subscription model class name.
     *
     * @param  string  $subscriptionModel
     * @return void
     */
    public static function useSubscriptionModel($subscriptionModel)
    {
        static::$subscriptionModel = $subscriptionModel;
    }

    /**
     * Configure Cashier to not register its migrations.
     *
     * @return static
     */
    public static function ignoreMigrations()
    {
        static::$runsMigrations = false;

        return new static;
    }

    /**
     * Configure Cashier to not register its routes.
     *
     * @return static
     */
    public static function ignoreRoutes()
    {
        static::$registersRoutes = false;

        return new static;
    }
}
