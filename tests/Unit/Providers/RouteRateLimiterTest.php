<?php

namespace Tests\Unit\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class RouteRateLimiterTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config(['ninja.environment' => 'hosted']);
    }

    public function test_daily_verify_uses_submitted_email_for_unauthenticated_requests(): void
    {
        $limits = $this->resolveLimits('daily-verify', Request::create('/api/v1/sms_reset', 'POST', [
            'email' => 'user@gmail.com',
        ]));

        $this->assertCount(2, $limits);
        $this->assertSame('user@gmail.com', $this->keyFor($limits[1]));
    }

    public function test_portal_login_limits_by_ip_and_email(): void
    {
        $limits = $this->resolveLimits('portal-login', Request::create('/client/login', 'POST', [
            'email' => 'contact@gmail.com',
        ], [], [], ['REMOTE_ADDR' => '203.0.113.10']));

        $this->assertCount(2, $limits);
        $this->assertSame('203.0.113.10', $this->keyFor($limits[0]));
        $this->assertSame('contact@gmail.com', $this->keyFor($limits[1]));
    }

    public function test_contact_login_limits_by_ip_and_email(): void
    {
        $limits = $this->resolveLimits('contact-login', Request::create('/api/v1/contact/login', 'POST', [
            'email' => 'contact@gmail.com',
        ], [], [], ['REMOTE_ADDR' => '203.0.113.11']));

        $this->assertCount(2, $limits);
        $this->assertSame('203.0.113.11', $this->keyFor($limits[0]));
        $this->assertSame('contact@gmail.com', $this->keyFor($limits[1]));
    }

    public function test_password_reset_limits_by_ip_and_email(): void
    {
        $limits = $this->resolveLimits('password-reset', Request::create('/password/email', 'POST', [
            'email' => 'user@gmail.com',
        ], [], [], ['REMOTE_ADDR' => '203.0.113.12']));

        $this->assertCount(2, $limits);
        $this->assertSame('203.0.113.12', $this->keyFor($limits[0]));
        $this->assertSame('user@gmail.com', $this->keyFor($limits[1]));
    }

    public function test_portal_auth_adds_email_limit_when_email_is_present(): void
    {
        $limits = $this->resolveLimits('portal-auth', Request::create('/client/password/email', 'POST', [
            'email' => 'contact@gmail.com',
        ], [], [], ['REMOTE_ADDR' => '203.0.113.13']));

        $this->assertCount(2, $limits);
        $this->assertSame('203.0.113.13', $this->keyFor($limits[0]));
        $this->assertSame('contact@gmail.com', $this->keyFor($limits[1]));
    }

    public function test_portal_auth_limits_ip_only_when_email_is_missing(): void
    {
        $limits = $this->resolveLimits('portal-auth', Request::create('/client/password/reset', 'POST', [], [], [], [
            'REMOTE_ADDR' => '203.0.113.14',
        ]));

        $this->assertCount(1, $limits);
        $this->assertSame('203.0.113.14', $this->keyFor($limits[0]));
    }

    public function test_signup_limits_by_ip_only(): void
    {
        $limits = $this->resolveLimits('signup', Request::create('/api/v1/signup', 'POST', [
            'email' => 'new@gmail.com',
        ], [], [], ['REMOTE_ADDR' => '203.0.113.15']));

        $this->assertCount(2, $limits);
        $this->assertSame(3, $limits[0]->maxAttempts);
        $this->assertSame(10, $limits[1]->maxAttempts);
        $this->assertStringStartsWith('203.0.113.15', $this->keyFor($limits[0]));
        $this->assertStringStartsWith('203.0.113.15', $this->keyFor($limits[1]));
    }

    public function test_contact_register_limits_by_ip_and_email(): void
    {
        $limits = $this->resolveLimits('contact-register', Request::create('/client/register', 'POST', [
            'email' => 'contact@gmail.com',
        ], [], [], ['REMOTE_ADDR' => '203.0.113.16']));

        $this->assertCount(2, $limits);
        $this->assertSame('203.0.113.16', $this->keyFor($limits[0]));
        $this->assertSame('contact@gmail.com', $this->keyFor($limits[1]));
    }

    /**
     * @return array<int, Limit>
     */
    private function resolveLimits(string $name, Request $request): array
    {
        $limiter = RateLimiter::limiter($name);

        $this->assertNotNull($limiter);

        $limits = $limiter($request);

        return is_array($limits) ? $limits : [$limits];
    }

    private function keyFor(Limit $limit): string
    {
        $reflection = new \ReflectionClass($limit);
        $property = $reflection->getProperty('key');
        $property->setAccessible(true);

        return (string) $property->getValue($limit);
    }
}
