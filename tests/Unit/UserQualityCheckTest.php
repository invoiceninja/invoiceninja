<?php

namespace Tests\Unit;

use App\Models\Account;
use App\Models\User;
use Modules\Admin\Jobs\Account\UserQualityCheck;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

class UserQualityCheckTest extends TestCase
{

    protected function setUp(): void
    {
        parent::setUp();

        if (
            ! class_exists(\Modules\Admin\Jobs\Account\UserQualityCheck::class)
            || ! class_exists(\Modules\Admin\Services\Spam\EmailDomainWebpageDetector::class)
        ) {
            $this->markTestSkipped('Admin module user quality checks are not installed.');
        }
    }

    
    public function testAggregatedNotificationIncludesAllAvailableData(): void
    {
        $job = new UserQualityCheck($this->user(), 'db-ninja-01');
        $method = new ReflectionMethod($job, 'buildNotificationContent');

        $content = $method->invoke(
            $job,
            [
                'Email domain does not have an A or AAAA record',
                'High-risk telephone prefix: +44',
                'IP country and telephone country do not match',
            ],
            'example.test',
            false,
            '+442079460958',
            ['GB'],
            'AU',
        );

        $message = $content[0];

        $this->assertStringContainsString('Email domain does not have an A or AAAA record', $message);
        $this->assertStringContainsString('High-risk telephone prefix: +44', $message);
        $this->assertStringContainsString('Telephone countries: [GB]', $message);
        $this->assertStringContainsString('IP country: [AU]', $message);
    }

    public function testAggregatedNotificationStaysBelowSlackRecommendedLimit(): void
    {
        $user = $this->user();
        $user->first_name = str_repeat('A', 5000);

        $job = new UserQualityCheck($user, 'db-ninja-01');
        $method = new ReflectionMethod($job, 'buildNotificationContent');

        $content = $method->invoke(
            $job,
            ['High-risk telephone prefix: +44'],
            'example.test',
            true,
            '+442079460958',
            ['GB'],
            'GB',
        );

        $this->assertLessThanOrEqual(3500, mb_strlen($content[0]));
    }

    private function user(): User
    {
        $account = new Account();
        $account->forceFill([
            'key' => 'account-key',
            'account_sms_verification_number' => '+442079460958',
        ]);

        $user = new User();
        $user->forceFill([
            'first_name' => 'Example',
            'last_name' => 'User',
            'email' => 'user@example.test',
            'ip' => '203.0.113.10',
        ]);
        $user->setRelation('account', $account);

        return $user;
    }
}
