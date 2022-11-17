<?php

namespace Tests\Unit;

use Mockery as m;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Jojostx\CashierPaystack\Http\Middleware\VerifyWebhookSignature;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Tests\TestCase;

class VerifyWebhookSignatureTest extends TestCase
{
    /**
     * @var \Illuminate\Http\Request
     */
    protected $request;

    public function setUp(): void
    {
        parent::setUp();

        config(['paystack.secretKey' => 'secret']);

        $this->request = new Request([], [], [], [], [], [], 'Signed Body');
        $this->request->setMethod('post');
    }

    public function test_signature_checks_out()
    {
        $this->withSignedSignature('secret');

        $response = (new VerifyWebhookSignature())
            ->handle($this->request, function ($request) {
                return new Response('OK');
            });

        $this->assertEquals('OK', $response->content());
    }

    public function test_bad_signature_aborts()
    {
        $this->withSignature('fail');

        $this->expectException(AccessDeniedHttpException::class);
        $this->expectExceptionMessage('No signatures found matching the expected signature for payload');

        (new VerifyWebhookSignature())
            ->handle($this->request, function ($request) {
            });
    }

    public function test_app_aborts_when_no_secret_was_provided()
    {
        $this->withSignedSignature('');

        $this->expectException(AccessDeniedHttpException::class);
        $this->expectExceptionMessage('No signatures found matching the expected signature for payload');

        (new VerifyWebhookSignature())
            ->handle($this->request, function ($request) {
            });
    }

    public function withSignedSignature($secret)
    {
        return $this->withSignature(
            $this->sign($this->request->getContent(), $secret)
        );
    }

    public function withSignature($signature)
    {
        $this->request->headers->set('HTTP_X_PAYSTACK_SIGNATURE', $signature);

        return $this;
    }

    private function sign($payload, $secret)
    {
        return hash_hmac('sha512', $payload, $secret);
    }
}
