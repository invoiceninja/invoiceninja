<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Company;
use App\Models\User;
use App\Notifications\Ninja\GenericNinjaAdminNotification;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Modules\Admin\Jobs\Account\UserQualityCheck;
use Modules\Admin\Services\Spam\EmailDomainWebpageDetector;
use ReflectionProperty;
use Tests\TestCase;

class UserQualityCheckFeatureTest extends TestCase
{
    use DatabaseTransactions;

    public function testItAggregatesAllTriggeredChecksIntoOneNotification(): void
    {
        Notification::fake();
        Http::preventStrayRequests();
        Http::fake([
            'ip-api.com/*' => Http::response(serialize([
                'status' => 'success',
                'countryCode' => 'AU',
            ])),
        ]);

        $this->app->instance(
            EmailDomainWebpageDetector::class,
            new EmailDomainWebpageDetector(static fn (string $domain): array => []),
        );

        $user = $this->makeUser(
            email: 'quality-check@domain-without-address-record.test',
            phoneNumber: '+442079460958',
            ip: '203.0.113.10',
        );

        (new UserQualityCheck($user, config('database.default')))->handle();

        Notification::assertSentOnDemand(
            GenericNinjaAdminNotification::class,
            function (GenericNinjaAdminNotification $notification): bool {
                $message = $this->notificationMessage($notification);

                $this->assertStringContainsString('Email domain does not have an A or AAAA record', $message);
                $this->assertStringContainsString('High-risk telephone prefix: +44', $message);
                $this->assertStringContainsString('IP country and telephone country do not match', $message);
                $this->assertStringContainsString('Telephone countries:', $message);
                $this->assertStringContainsString('GB', $message);
                $this->assertStringContainsString('IP country: [AU]', $message);

                return true;
            },
        );

        Notification::assertCount(1);
    }

    public function testItDoesNotNotifyWhenAllChecksPass(): void
    {
        Notification::fake();
        Http::preventStrayRequests();
        Http::fake([
            'ip-api.com/*' => Http::response(serialize([
                'status' => 'success',
                'countryCode' => 'AU',
            ])),
        ]);

        $this->app->instance(
            EmailDomainWebpageDetector::class,
            new EmailDomainWebpageDetector(static fn (string $domain): array => [
                ['host' => $domain, 'type' => 'A', 'ip' => '93.184.216.34'],
            ]),
        );

        $user = $this->makeUser(
            email: 'quality-check@example.com',
            phoneNumber: '+61293744000',
            ip: '203.0.113.10',
        );

        (new UserQualityCheck($user, config('database.default')))->handle();

        Notification::assertNothingSent();
    }

    private function makeUser(string $email, string $phoneNumber, string $ip): User
    {
        $account = Account::factory()->create([
            'account_sms_verification_number' => $phoneNumber,
        ]);

        $company = Company::factory()->create([
            'account_id' => $account->id,
            'db' => config('database.default'),
        ]);

        $account->default_company_id = $company->id;
        $account->save();

        return User::factory()->create([
            'account_id' => $account->id,
            'email' => $email,
            'phone' => $phoneNumber,
            'ip' => $ip,
        ]);
    }

    private function notificationMessage(GenericNinjaAdminNotification $notification): string
    {
        $property = new ReflectionProperty($notification, 'message_array');

        return implode("\n", $property->getValue($notification));
    }
}
