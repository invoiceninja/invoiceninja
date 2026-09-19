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

use App\Mail\Engine\CreditEmailEngine;
use App\Mail\Engine\InvoiceEmailEngine;
use App\Mail\Engine\PaymentEmailEngine;
use App\Mail\Engine\PurchaseOrderEmailEngine;
use App\Mail\Engine\QuoteEmailEngine;
use App\Models\CreditInvitation;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Session;
use Tests\MockAccountData;
use Tests\TestCase;

/**
 * Asserts that the text/plain MIME alternative of entity emails reflects
 * the actual (custom) email body, instead of either a hardcoded generic
 * message (Invoice/Quote/Credit/PurchaseOrder) or the raw un-stripped HTML
 * body (Payment).
 *
 * @see \App\Mail\Engine\BaseEmailEngine::setTextBody()
 */
class EntityEmailTextBodyTest extends TestCase
{
    use MockAccountData;
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        Session::start();
        Model::reguard();

        $this->makeTestData();
    }

    private function customBody(string $marker): string
    {
        return "<p>Hello,</p><p>{$marker} with a <strong>bold</strong> word.</p><p>\$view_button</p>";
    }

    /**
     * A rich-text email template editor saves paragraphs as <p>, not <br> -
     * assert the <p> boundaries survive as actual line breaks in the
     * plain-text part, not just that the words are present somewhere.
     */
    private function assertParagraphsAreSeparated(string $marker, string $text_body): void
    {
        $this->assertMatchesRegularExpression(
            '/Hello,\s*[\r\n]+\s*' . preg_quote($marker, '/') . '/',
            $text_body,
            "Expected 'Hello,' and '{$marker}' to be on separate lines, not run together."
        );
    }

    public function testInvoiceEmailEngineTextBodyMatchesCustomBody(): void
    {
        $invitation = $this->invoice->invitations->first();

        $engine = new InvoiceEmailEngine($invitation, 'invoice', [
            'body' => $this->customBody('This is the custom invoice message'),
            'subject' => 'Invoice subject',
        ]);
        $engine->build();

        $text_body = $engine->getTextBody();

        $this->assertStringContainsString('This is the custom invoice message', $text_body);
        $this->assertStringNotContainsString('<strong>', $text_body);
        $this->assertStringNotContainsString('<p>', $text_body);
        $this->assertStringContainsString($invitation->getLink(), $text_body);
        $this->assertParagraphsAreSeparated('This is the custom invoice message', $text_body);
    }

    public function testQuoteEmailEngineTextBodyMatchesCustomBody(): void
    {
        $invitation = $this->quote->invitations->first();

        $engine = new QuoteEmailEngine($invitation, 'quote', [
            'body' => $this->customBody('This is the custom quote message'),
            'subject' => 'Quote subject',
        ]);
        $engine->build();

        $text_body = $engine->getTextBody();

        $this->assertStringContainsString('This is the custom quote message', $text_body);
        $this->assertStringNotContainsString('<strong>', $text_body);
        $this->assertStringNotContainsString('<p>', $text_body);
        $this->assertStringContainsString($invitation->getLink(), $text_body);
        $this->assertParagraphsAreSeparated('This is the custom quote message', $text_body);
    }

    public function testCreditEmailEngineTextBodyMatchesCustomBody(): void
    {
        // MockAccountData doesn't always seed a CreditInvitation explicitly,
        // but saving the credit can create one via a model observer.
        $invitation = $this->credit->invitations()->first() ?? CreditInvitation::factory()->create([
            'user_id' => $this->credit->user_id,
            'company_id' => $this->company->id,
            'client_contact_id' => $this->contact->id,
            'credit_id' => $this->credit->id,
        ]);

        $engine = new CreditEmailEngine($invitation, 'credit', [
            'body' => $this->customBody('This is the custom credit message'),
            'subject' => 'Credit subject',
        ]);
        $engine->build();

        $text_body = $engine->getTextBody();

        $this->assertStringContainsString('This is the custom credit message', $text_body);
        $this->assertStringNotContainsString('<strong>', $text_body);
        $this->assertStringNotContainsString('<p>', $text_body);
        $this->assertStringContainsString($invitation->getLink(), $text_body);
        $this->assertParagraphsAreSeparated('This is the custom credit message', $text_body);
    }

    public function testPurchaseOrderEmailEngineTextBodyMatchesCustomBody(): void
    {
        $invitation = $this->purchase_order->invitations->first();

        $engine = new PurchaseOrderEmailEngine($invitation, 'purchase_order', [
            'body' => $this->customBody('This is the custom purchase order message'),
            'subject' => 'Purchase order subject',
        ]);
        $engine->build();

        $text_body = $engine->getTextBody();

        $this->assertStringContainsString('This is the custom purchase order message', $text_body);
        $this->assertStringNotContainsString('<strong>', $text_body);
        $this->assertStringNotContainsString('<p>', $text_body);
        $this->assertStringContainsString($invitation->getLink(), $text_body);
        $this->assertParagraphsAreSeparated('This is the custom purchase order message', $text_body);
    }

    public function testPaymentEmailEngineTextBodyStripsHtml(): void
    {
        $engine = new PaymentEmailEngine($this->payment, $this->contact, [
            'body' => $this->customBody('This is the custom payment receipt message'),
            'subject' => 'Payment subject',
        ]);
        $engine->build();

        $text_body = $engine->getTextBody();

        $this->assertStringContainsString('This is the custom payment receipt message', $text_body);
        $this->assertStringNotContainsString('<strong>', $text_body);
        $this->assertStringNotContainsString('<p>', $text_body);
        $this->assertParagraphsAreSeparated('This is the custom payment receipt message', $text_body);
    }
}
