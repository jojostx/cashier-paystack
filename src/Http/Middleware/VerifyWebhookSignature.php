<?php
namespace Wisdomanthoni\Cashier\Http\Middleware;

use Closure;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\Config\Repository as Config;

final class VerifyWebhookSignature
{
    /**
     * The application instance.
     *
     * @var \Illuminate\Contracts\Foundation\Application
     */
    protected $app;
    /**
     * The configuration repository instance.
     *
     * @var \Illuminate\Contracts\Config\Repository
     */
    protected $config;
    /**
     * Create a new middleware instance.
     *
     * @param  \Illuminate\Contracts\Foundation\Application  $app
     * @param  \Illuminate\Contracts\Config\Repository  $config
     * @return void
     */
    public function __construct(Application $app, Config $config)
    {
        $this->app = $app;
        $this->config = $config;
    }
    /**
     * Handle the incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return \Illuminate\Http\Response
     */
    public function handle($request, Closure $next)
    {
        // only a post with paystack signature header gets our attention
        if (!$request->headers->has('x-paystack-signature')) 
            $this->app->abort(403);

        // validate event do all at once to avoid timing attack
        if($request->header('x-paystack-signature') === $this->sign($request->getContent(), $this->config->get('paystack.secretKey'))) 
            $this->app->abort(403);

        return $next($request);
    }

    /**
     * Sign request
     *
     * @param array $payload
     * @param string $secret
     * @return string
     */
    private function sign(array $payload, string $secret)
    {
        return hash_hmac('sha512', $payload, $secret);
    }
}