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

use App\DataMapper\ClientSettings;
use App\Jobs\Mail\NinjaMailer;
use App\Mail\Admin\ClientPaymentFailureObject;
use App\Mail\Admin\PaymentFailureObject;
use App\Models\PaymentHash;
use App\Utils\Number;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\MockAccountData;
use Tests\TestCase;

class ClientPaymentFailureEmailTest extends TestCase
{
    use DatabaseTransactions;
    use MockAccountData;

    private PaymentHash $payment_hash;

    protected function setUp(): void
    {
        parent::setUp();

        $this->makeTestData();

        $settings = $this->company->settings;
        $settings->email_style = 'custom';
        $settings->email_style_custom = '<section id="custom-layout"><header>$invoice.number</header>$body<footer>$signature</footer></section>';
        $settings->email_subject_payment_failed = 'Failed payment for $invoice.number';
        $settings->email_template_payment_failed = '<p>Payment of $total failed: $payment_error</p>';
        $settings->email_signature = 'Payment support';
        $this->company->settings = $settings;
        $this->company->save();

        $this->client->settings = ClientSettings::defaults();
        $this->client->group_settings_id = null;
        $this->client->save();
        $this->client->setRelation('group_settings', null);
        $this->client->setRelation('company', $this->company);

        $this->payment_hash = new PaymentHash();
        $this->payment_hash->data = [
            'amount_with_fee' => 25,
            'invoices' => [
                ['invoice_id' => $this->invoice->hashed_id],
            ],
        ];
    }

    public function testCustomLayoutWrapsTheConfiguredBodyAndResolvesVariables(): void
    {
        $mail_obj = $this->buildClientEmail();
        $html = (new NinjaMailer($mail_obj))->render();
        $amount = Number::formatMoney(25, $this->client);

        $this->assertSame('email.template.custom', $mail_obj->markdown);
        $this->assertSame('Failed payment for '.$this->invoice->number, $mail_obj->subject);
        $this->assertStringContainsString(
            '<section id="custom-layout"><header>'.$this->invoice->number.'</header><p>Payment of '.$amount.' failed: Card declined</p><footer>Payment support</footer></section>',
            $html
        );
        $this->assertStringNotContainsString('id="content"', $html);
        $this->assertSame('email.template.text', $mail_obj->text_view);
        $this->assertStringNotContainsString('custom-layout', $mail_obj->data['text_body']);
    }

    public function testClientCustomLayoutOverridesTheCompanyLayout(): void
    {
        $settings = $this->client->settings;
        $settings->email_style_custom = '<article id="client-layout">$body</article>';
        $this->client->settings = $settings;
        $this->client->save();

        $html = (new NinjaMailer($this->buildClientEmail()))->render();

        $this->assertStringContainsString('<article id="client-layout"><p>Payment of ', $html);
        $this->assertStringContainsString('Card declined</p></article>', $html);
        $this->assertStringNotContainsString('custom-layout', $html);
    }

    #[DataProvider('emailStyleProvider')]
    public function testClientEmailStyleSelectsTheExpectedView(string $email_style, string $expected_view): void
    {
        $settings = $this->client->settings;
        $settings->email_style = $email_style;
        $this->client->settings = $settings;
        $this->client->save();

        $mail_obj = $this->buildClientEmail();
        $mailable = (new NinjaMailer($mail_obj))->build();

        $this->assertSame($expected_view, $mailable->view);
        $this->assertStringContainsString('Card declined', $mail_obj->data['body']);
        $this->assertStringNotContainsString('custom-layout', $mail_obj->data['body']);
        $this->assertSame('Payment support', $mail_obj->data['signature']);

        if ($email_style === 'premium') {
            return;
        }

        $html = $mailable->render();

        $this->assertStringContainsString('Card declined', $html);
        $this->assertStringNotContainsString('custom-layout', $html);

        if ($email_style === 'plain') {
            $this->assertStringContainsString('id="content-wrapper"', $html);
            $this->assertStringNotContainsString('id="content"', $html);
        } elseif ($email_style === 'dark') {
            $this->assertStringContainsString('background-color: #1a1a1a !important;', $html);
        }
    }

    public static function emailStyleProvider(): array
    {
        return [
            'light' => ['light', 'email.template.client'],
            'dark' => ['dark', 'email.template.client'],
            'plain' => ['plain', 'email.template.plain'],
            'premium' => ['premium', 'email.template.client_premium'],
            'fallback' => ['unknown', 'email.template.client'],
        ];
    }

    public function testAdminNotificationRetainsItsOwnLayout(): void
    {
        $mail_obj = (new PaymentFailureObject(
            $this->client,
            'Card declined',
            $this->company,
            25,
            $this->payment_hash
        ))->build();

        $this->assertSame('email.admin.generic', $mail_obj->markdown);
        $this->assertStringNotContainsString('custom-layout', $mail_obj->data['content']);
        $this->assertSame('Card declined', $mail_obj->data['additional_info']);
    }

    private function buildClientEmail(): \stdClass
    {
        return (new ClientPaymentFailureObject(
            $this->client,
            'Card declined',
            $this->company,
            $this->payment_hash
        ))->build();
    }
}
