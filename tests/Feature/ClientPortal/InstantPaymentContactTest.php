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

namespace Tests\Feature\ClientPortal;

use App\Services\ClientPortal\InstantPayment;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Tests\MockAccountData;
use Tests\TestCase;

class InstantPaymentContactTest extends TestCase
{
    use DatabaseTransactions;
    use MockAccountData;

    protected function setUp(): void
    {
        parent::setUp();

        $this->makeTestData();
    }

    public function testMissingContactEmailDoesNotWipeStoredEmail(): void
    {
        $contact = $this->client->contacts()->where('is_primary', true)->first();
        $contact->email = 'keep-me@example.org';
        $contact->first_name = 'Original';
        $contact->last_name = 'Name';
        $contact->save();

        $this->actingAs($contact, 'contact');

        $request = Request::create('/client/payments/process', 'POST', [
            'company_gateway_id' => 1,
            'payment_method_id' => 1,
            'payable_invoices' => [],
            'contact_first_name' => '',
            'contact_last_name' => '',
            'contact_email' => '',
        ]);

        (new InstantPayment($request))->run();

        $contact->refresh();

        $this->assertSame('keep-me@example.org', $contact->email);
        $this->assertSame('Original', $contact->first_name);
        $this->assertSame('Name', $contact->last_name);
    }

    public function testInvalidContactEmailDoesNotOverwriteStoredEmail(): void
    {
        $contact = $this->client->contacts()->where('is_primary', true)->first();
        $contact->email = 'keep-me@example.org';
        $contact->save();

        $this->actingAs($contact, 'contact');

        $request = Request::create('/client/payments/process', 'POST', [
            'company_gateway_id' => 1,
            'payment_method_id' => 1,
            'payable_invoices' => [],
            'contact_email' => 'not-an-email',
        ]);

        (new InstantPayment($request))->run();

        $this->assertSame('keep-me@example.org', $contact->fresh()->email);
    }

    public function testValidContactEmailUpdatesStoredEmail(): void
    {
        $contact = $this->client->contacts()->where('is_primary', true)->first();
        $contact->email = 'old@example.org';
        $contact->save();

        $this->actingAs($contact, 'contact');

        $request = Request::create('/client/payments/process', 'POST', [
            'company_gateway_id' => 1,
            'payment_method_id' => 1,
            'payable_invoices' => [],
            'contact_email' => 'new@example.org',
            'contact_first_name' => 'Updated',
            'contact_last_name' => 'Person',
        ]);

        (new InstantPayment($request))->run();

        $contact->refresh();

        $this->assertSame('new@example.org', $contact->email);
        $this->assertSame('Updated', $contact->first_name);
        $this->assertSame('Person', $contact->last_name);
    }
}
