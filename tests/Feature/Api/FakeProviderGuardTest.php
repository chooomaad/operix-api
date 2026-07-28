<?php

namespace Tests\Feature\Api;

use App\Payments\FakePaymentProvider;
use App\Payments\PaymentProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FakeProviderGuardTest extends TestCase
{
    use RefreshDatabase;

    public function test_fake_provider_is_forbidden_in_production(): void
    {
        config(['operix.payment.provider' => 'fake']);
        $this->app['env'] = 'production';
        $this->app->forgetInstance(PaymentProvider::class);

        $this->expectException(\RuntimeException::class);

        app(PaymentProvider::class);
    }

    public function test_fake_provider_is_allowed_outside_production(): void
    {
        config(['operix.payment.provider' => 'fake']);
        $this->app['env'] = 'testing';
        $this->app->forgetInstance(PaymentProvider::class);

        $this->assertInstanceOf(FakePaymentProvider::class, app(PaymentProvider::class));
    }
}
