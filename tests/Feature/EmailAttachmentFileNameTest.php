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

use App\Models\Invoice;
use App\Services\Email\Email;
use App\Services\Email\EmailDefaults;
use App\Services\Email\EmailObject;
use App\Services\Invoice\InvoiceService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\MockAccountData;
use Tests\TestCase;

class EmailAttachmentFileNameTest extends TestCase
{
    use MockAccountData;
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        Model::reguard();

        $this->makeTestData();
    }

    public function testEInvoiceAttachmentKeepsADottedNumber(): void
    {
        $names = $this->attachmentNames($this->invoiceWithEDocument('2024.001'));

        $this->assertSame([str_replace(' ', '_', ctrans('texts.invoice')) . '_2024.001-e_invoice.xml'], $names);
    }

    /**
     * The email attachment names for an invoice whose e-invoice is attached on
     * its own (not merged into the PDF), with the PDF left out.
     */
    private function attachmentNames(Invoice $invoice): array
    {
        $settings = $this->client->getMergedSettings();
        $settings->pdf_email_attachment = false;
        $settings->ubl_email_attachment = false;
        $settings->enable_e_invoice = true;
        $settings->merge_e_invoice_to_pdf = false;
        $settings->document_email_attachment = false;

        $email_object = new EmailObject();
        $email_object->entity = $invoice;
        $email_object->invitation = $this->invoice->invitations()->first();
        $email_object->settings = $settings;
        $email_object->email_template_subject = 'email_subject_invoice';

        $defaults = new EmailDefaults(new Email($email_object, $this->company));
        (new \ReflectionMethod(EmailDefaults::class, 'setAttachments'))->invoke($defaults);

        return array_column($email_object->attachments, 'name');
    }

    /** The test invoice with [number], whose e-invoice is a stub. */
    private function invoiceWithEDocument(string $number): Invoice
    {
        $invoice = new class () extends Invoice {
            protected $table = 'invoices';

            public function service(): InvoiceService
            {
                $service = \Mockery::mock(InvoiceService::class);
                $service->shouldReceive('getEDocument')->andReturn('<Invoice/>');

                return $service;
            }
        };

        $invoice->setRawAttributes(array_merge($this->invoice->getAttributes(), ['number' => $number]), true);
        $invoice->exists = true;

        return $invoice;
    }
}
