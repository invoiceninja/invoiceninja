<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Company;
use App\Notifications\Ninja\GenericNinjaAdminNotification;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class AccountStatusTest extends TestCase
{
    use DatabaseTransactions;

    private const ACCOUNT_STATUS_JOB = '\\Modules\\Admin\\Jobs\\Account\\AccountStatus';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'ninja.environment' => 'hosted',
            'ninja.notification.slack' => 'https://hooks.slack.test/account-status',
        ]);

        if(! class_exists(self::ACCOUNT_STATUS_JOB)) {
            $this->markTestSkipped('Admin module is not installed.');
        }
        
    }

    public function testItNotifiesWithinTheReviewWindow(): void
    {
        Notification::fake();

        [$account, $account_created_at] = $this->makeContext(now()->subMinutes(4));

        $this->handle($account, $account_created_at);

        Notification::assertSentOnDemand(GenericNinjaAdminNotification::class);
    }

    public function testItDoesNotNotifyOutsideTheReviewWindow(): void
    {
        Notification::fake();

        [$account, $account_created_at] = $this->makeContext(now()->subMinutes(6));

        $this->handle($account, $account_created_at);

        Notification::assertNothingSent();
    }

    public function testItDoesNotNotifyWithoutTheCapturedAccountTimestamp(): void
    {
        Notification::fake();

        [$account] = $this->makeContext(now()->subMinutes(4));

        $this->handle($account, 0);

        Notification::assertNothingSent();
    }

    public function testTrialHookDoesNotRequireTheAdminJobAtLoadTime(): void
    {
        $controller = (string) file_get_contents(base_path('app/Http/Controllers/ClientPortal/NinjaPlanController.php'));

        $this->assertStringContainsString('class_exists(\\Modules\\Admin\\Jobs\\Account\\AccountStatus::class)', $controller);
        $this->assertStringContainsString('$account_created_at', $controller);
        $this->assertStringNotContainsString('use Modules\\Admin\\Jobs\\Account\\AccountStatus;', $controller);
        $this->assertStringNotContainsString('$billing_database', $controller);
        $this->assertStringNotContainsString('$trial_started_timestamp', $controller);
    }

    private function handle(Account $account, int $account_created_at): void
    {
        if (! class_exists(self::ACCOUNT_STATUS_JOB)) {
            $this->markTestSkipped('Admin module is not installed.');
        }

        $job_class = self::ACCOUNT_STATUS_JOB;

        (new $job_class(
            (string) $account->key,
            $account_created_at
        ))->handle();
    }

    private function makeContext(mixed $account_created_at): array
    {
        $account = Account::factory()->create([
            'created_at' => now(),
            'is_flagged' => false,
            'is_trial' => true,
            'plan' => 'pro',
            'plan_paid' => null,
        ]);

        $company = Company::factory()->create(['account_id' => $account->id]);
        $account->default_company_id = $company->id;
        $account->save();

        config(['ninja.ninja_default_company_id' => $company->id]);

        return [$account->fresh(), $this->formatTimestamp($account_created_at)];
    }

    private function formatTimestamp(mixed $value): int
    {
        if ($value instanceof \DateTimeInterface) {
            return $value->getTimestamp();
        }

        return $value ? (int) $value : 0;
    }
}