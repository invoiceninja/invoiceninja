<?php

/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2026. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace Tests\Feature;

use App\Jobs\Mail\NinjaMailerJob;
use App\Models\Account;
use App\Models\Company;
use App\Services\Email\Email;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Covers the email quota counter in NinjaMailerJob and the Email service.
 *
 * The counter must only increment when our shared infrastructure is used.
 * It must not increment for customer-credential mailers (client_postmark,
 * client_mailgun, client_ses) which collapse onto the shared driver names.
 */
class EmailQuotaCounterTest extends TestCase
{
    private Account $account;

    private Company $company;

    protected function setUp(): void
    {
        parent::setUp();

        $this->account = new Account();
        $this->account->key = Str::random(32);

        $this->company = new Company();
        $this->company->setRelation('account', $this->account);

        Cache::forget('email_quota'.$this->account->key);
    }

    protected function tearDown(): void
    {
        Cache::forget('email_quota'.$this->account->key);

        parent::tearDown();
    }

    /**
     * @param array<string, mixed> $secrets
     */
    private function invokeIncrement(string $class, string $mailer, array $secrets = []): void
    {
        $reflection = new \ReflectionClass($class);

        $instance = $reflection->newInstanceWithoutConstructor();

        $properties = array_merge([
            'company' => $this->company,
            'mailer' => $mailer,
        ], $secrets);

        foreach ($properties as $name => $value) {
            $reflection->getProperty($name)->setValue($instance, $value);
        }

        $reflection->getMethod('incrementEmailCounter')->invoke($instance);
    }

    /**
     * @return array<string, array{0: string, 1: array<string, mixed>, 2: int|null}>
     */
    public static function mailerProvider(): array
    {
        return [
            'shared postmark counts' => ['postmark', [], 1],
            'shared mailgun counts' => ['mailgun', [], 1],
            'shared ses counts' => ['ses', [], 1],
            'gmail is uncapped' => ['gmail', [], null],
            'office365 is uncapped' => ['office365', [], null],
            'smtp is uncapped' => ['smtp', [], null],
            'client postmark is excluded' => ['postmark', ['client_postmark_secret' => 'spoofed-token'], null],
            'client mailgun is excluded' => ['mailgun', ['client_mailgun_secret' => 'spoofed-token'], null],
            'client ses is excluded' => ['ses', ['client_ses_secret' => true], null],
        ];
    }

    /**
     * @param array<string, mixed> $secrets
     */
    #[DataProvider('mailerProvider')]
    public function testNinjaMailerJobQuotaCounter(string $mailer, array $secrets, ?int $expected): void
    {
        $this->invokeIncrement(NinjaMailerJob::class, $mailer, $secrets);

        $this->assertEquals($expected, Cache::get('email_quota'.$this->account->key));
    }

    /**
     * @param array<string, mixed> $secrets
     */
    #[DataProvider('mailerProvider')]
    public function testEmailServiceQuotaCounter(string $mailer, array $secrets, ?int $expected): void
    {
        $this->invokeIncrement(Email::class, $mailer, $secrets);

        $this->assertEquals($expected, Cache::get('email_quota'.$this->account->key));
    }
}
