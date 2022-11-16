<?php

namespace Tests\Feature;

use Jojostx\CashierPaystack\CashierServiceProvider;
use Orchestra\Testbench\TestCase;
use Tests\Fixtures\User;
use Illuminate\Database\Eloquent\Model as Eloquent;
use Illuminate\Foundation\Testing\RefreshDatabase;

abstract class FeatureTestCase extends TestCase
{
    use RefreshDatabase;
    
    protected function setUp(): void
    {
        parent::setUp();

        Eloquent::unguard();

        $this->artisan('migrate')->run();
    }

    protected function getPackageProviders($app)
    {
        return [CashierServiceProvider::class];
    }

    /**
     * Define environment setup.
     *
     * @param  \Illuminate\Foundation\Application  $app
     * @return void
     */
    protected function defineEnvironment($app)
    {
        // Setup default database to use sqlite :memory:
        $app['config']->set('database.default', 'testbench');
        $app['config']->set('database.connections.testbench', [
            'driver'   => 'sqlite',
            'database' => ':memory:',
            'prefix'   => '',
        ]);

        $app['config']->set('paystack', [
            'publicKey' => getenv('PAYSTACK_PUBLIC_KEY'),
            'secretKey' => getenv('PAYSTACK_SECRET_KEY'),
            'paymentUrl' => getenv('PAYSTACK_PAYMENT_URL'),
            'merchantEmail' => getenv('MERCHANT_EMAIL'),
        ]);
    }

    /**
     * Define database migrations.
     *
     * @return void
     */
    protected function defineDatabaseMigrations()
    {
        $this->loadLaravelMigrations(['--database' => 'testbench']);
    }

    protected function createBillable($description = 'taylor', array $options = []): User
    {
        $user = $this->createUser($description);

        $user->createAsCustomer($options);

        return $user;
    }

    protected function createUser($description = 'taylor', array $options = []): User
    {
        return User::create(array_merge([
            'email' => "{$description}@paystack-test.com",
            'name' => 'Taylor Otwell',
            'email_verified_at' => now(),
            'password' => '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
        ], $options));
    }
}
