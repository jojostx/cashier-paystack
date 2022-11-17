<?php

namespace Jojostx\CashierPaystack\Http\Middleware;

use Closure;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class VerifyWebhookSignature
{
    /**
     * Handle the incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return \Illuminate\Http\Response
     */
    public function handle($request, Closure $next)
    {
        // validate that callback is coming from Paystack
        if ((!$request->isMethod('post')) || !$request->header('HTTP_X_PAYSTACK_SIGNATURE', null)) {
            throw new AccessDeniedHttpException("Invalid Request");
        }

        $input = $request->getContent();
        $paystack_key = config('paystack.secretKey');

        if ($request->header('HTTP_X_PAYSTACK_SIGNATURE') !== $this->sign($input, $paystack_key)) {
            throw new AccessDeniedHttpException('No signatures found matching the expected signature for payload');
        }

        return $next($request);
    }

    /**
     * Sign request
     *
     * @param string $payload
     * @param string $secret
     * @return string
     */
    private function sign(string $payload, string $secret)
    {
        return hash_hmac('sha512', $payload, $secret);
    }
}
