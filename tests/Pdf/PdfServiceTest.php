<?php

/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2021. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace Tests\Pdf;

use Tests\TestCase;
use App\Models\Client;
use App\Models\Vendor;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\Payment;
use Tests\MockAccountData;
use App\Models\ClientContact;
use App\Models\PurchaseOrder;
use App\Models\VendorContact;
use App\Services\Pdf\PdfService;
use App\DataMapper\CompanySettings;
use App\Services\Pdf\PdfConfiguration;
use App\Services\Template\TemplateAction;
use App\Services\Template\TemplateService;
use Str;

/**
 * 
 *   App\Services\Pdf\PdfService
 */
class PdfServiceTest extends TestCase
{
    use MockAccountData;

    private string $max_pdf_variables = '{"client_details":["$client.name","$contact.full_name","$client.address1","$client.city_state_postal","$client.number","$client.vat_number","$client.postal_city_state","$client.website","$client.country","$client.custom3","$client.id_number","$client.phone","$client.address2","$client.custom1","$contact.custom1"],"vendor_details":["$vendor.name","$vendor.number","$vendor.vat_number","$vendor.address1","$vendor.address2","$vendor.city_state_postal","$vendor.country","$vendor.phone","$contact.email","$vendor.id_number","$vendor.website","$vendor.custom2","$vendor.custom1","$vendor.custom4","$vendor.custom3","$contact.phone","$contact.full_name","$contact.custom2","$contact.custom1"],"purchase_order_details":["$purchase_order.number","$purchase_order.date","$purchase_order.total","$purchase_order.balance_due","$purchase_order.due_date","$purchase_order.po_number","$purchase_order.custom1","$purchase_order.custom2","$purchase_order.custom3"],"company_details":["$company.name","$company.email","$company.phone","$company.id_number","$company.vat_number","$company.website","$company.address2","$company.address1","$company.city_state_postal","$company.postal_city_state","$company.custom1","$company.custom3"],"company_address":["$company.address1","$company.city_state_postal","$company.country","$company.id_number","$company.vat_number","$company.website","$company.email","$company.name","$company.custom1"],"invoice_details":["$invoice.number","$invoice.date","$invoice.balance","$invoice.custom1","$invoice.due_date","$invoice.project","$invoice.balance_due","$invoice.custom3","$invoice.po_number","$invoice.custom2","$invoice.amount","$invoice.custom4"],"quote_details":["$quote.number","$quote.custom1","$quote.po_number","$quote.date","$quote.valid_until","$quote.total","$quote.custom2","$quote.custom3","$quote.custom4"],"credit_details":["$credit.number","$credit.balance","$credit.po_number","$credit.date","$credit.valid_until","$credit.total","$credit.custom1","$credit.custom2","$credit.custom3"],"product_columns":["$product.item","$product.product1","$product.description","$product.product2","$product.tax","$product.line_total","$product.quantity","$product.unit_cost","$product.discount","$product.product3","$product.product4","$product.gross_line_total"],"product_quote_columns":["$product.item","$product.description","$product.unit_cost","$product.quantity","$product.discount","$product.tax","$product.line_total"],"task_columns":["$task.service","$task.description","$task.rate","$task.hours","$task.discount","$task.line_total","$task.tax","$task.tax_amount","$task.task2","$task.task1","$task.task3"],"total_columns":["$total","$line_taxes","$total_taxes","$discount","$custom_surcharge1","$outstanding","$net_subtotal","$custom_surcharge2","$custom_surcharge3","$subtotal","$paid_to_date"],"statement_invoice_columns":["$invoice.number","$invoice.date","$due_date","$total","$balance"],"statement_payment_columns":["$invoice.number","$payment.date","$method","$statement_amount"],"statement_credit_columns":["$credit.number","$credit.date","$total","$credit.balance"],"statement_details":["$statement_date","$balance"],"delivery_note_columns":["$product.item","$product.description","$product.quantity"],"statement_unapplied_columns":["$payment.number","$payment.date","$payment.amount","$payment.payment_balance"]}';
    
    private string $min_pdf_variables = '{"client_details":["$client.name","$client.vat_number","$client.address1","$client.city_state_postal","$client.country"],"vendor_details":["$vendor.name","$vendor.vat_number","$vendor.address1","$vendor.city_state_postal","$vendor.country"],"purchase_order_details":["$purchase_order.number","$purchase_order.date","$purchase_order.total"],"company_details":["$company.name","$company.address1","$company.city_state_postal"],"company_address":["$company.name","$company.website"],"invoice_details":["$invoice.number","$invoice.date","$invoice.due_date","$invoice.balance"],"quote_details":["$quote.number","$quote.date","$quote.valid_until"],"credit_details":["$credit.date","$credit.number","$credit.balance"],"product_columns":["$product.item","$product.description","$product.line_total"],"product_quote_columns":["$product.item","$product.description","$product.unit_cost","$product.quantity","$product.discount","$product.tax","$product.line_total"],"task_columns":["$task.description","$task.rate","$task.line_total"],"total_columns":["$total","$total_taxes","$outstanding"],"statement_invoice_columns":["$invoice.number","$invoice.date","$due_date","$total","$balance"],"statement_payment_columns":["$invoice.number","$payment.date","$method","$statement_amount"],"statement_credit_columns":["$credit.number","$credit.date","$total","$credit.balance"],"statement_details":["$statement_date","$balance"],"delivery_note_columns":["$product.item","$product.description","$product.quantity"],"statement_unapplied_columns":["$payment.number","$payment.date","$payment.amount","$payment.payment_balance"]}';

    private string $fake_email;

    private array $template_designs = [
        'delivery_notes' => [
            '/views/templates/delivery_notes/td4.html',
            '/views/templates/delivery_notes/td5.html',
            '/views/templates/delivery_notes/td12.html',
            '/views/templates/delivery_notes/td13.html',
        ],
        'payments' => [
            '/views/templates/payments/tp6.html',
            '/views/templates/payments/tp7.html',
            '/views/templates/payments/tr8.html',
            '/views/templates/payments/tr9.html',
        ],
        'projects' => [
            '/views/templates/projects/tp11.html',
        ],
        'statements' => [
            '/views/templates/statements/ts1.html',
            '/views/templates/statements/ts2.html',
            '/views/templates/statements/ts3.html',
            '/views/templates/statements/ts4.html',
        ],
        'tasks' => [
            '/views/templates/tasks/tt10.html',
        ],
    ];

    protected function setUp(): void
    {
        parent::setUp();

        $this->makeTestData();

        $this->fake_email = $this->faker->email();

    }

    private function stubInvoice($settings, array $company_props = [])
    {
                
        $company = Company::factory()->create(array_merge([
            'account_id' => $this->account->id,
            'settings' => $settings
        ], $company_props));

        $client = Client::factory()->create([
            'user_id' => $this->user->id,
            'company_id' => $company->id
        ]);

        $contact = ClientContact::factory()->create([
            'user_id' => $this->user->id,
            'company_id' => $company->id,
            'client_id' => $client->id,
            'is_primary' => true,
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email' => 'john@doe.com',
            'phone' => '1234567890',
            'send_email' => true,
        ]);

        $invoice = Invoice::factory()->create([
            'user_id' => $this->user->id,
            'company_id' => $company->id,
            'client_id' => $client->id,
            'status_id' => Invoice::STATUS_DRAFT,
        ]);

        $invoice = $invoice->calc()->getInvoice();
        $invoice = $invoice->service()->createInvitations()->markSent()->save();
        $invoice = $invoice->fresh();

        return $invoice;
    }

    private function stubPurchaseOrder($settings, array $company_props = [])
    {

        $company = Company::factory()->create(array_merge([
                    'account_id' => $this->account->id,
                    'settings' => $settings
                ], $company_props));

        $vendor = Vendor::factory()->create([
            'user_id' => $this->user->id,
            'company_id' => $company->id
        ]);

        $contact = VendorContact::factory()->create([
            'user_id' => $this->user->id,
            'company_id' => $company->id,
            'vendor_id' => $vendor->id,
            'is_primary' => true,
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email' => 'john@doe.com',
            'phone' => '1234567890',
            'send_email' => true,
        ]);

        $po = PurchaseOrder::factory()->create([
            'user_id' => $this->user->id,
            'company_id' => $company->id,
            'vendor_id' => $vendor->id,
            'status_id' => PurchaseOrder::STATUS_DRAFT,
        ]);

        $po = $po->calc()->getInvoice();
        $po = $po->service()->createInvitations()->markSent()->save();
        $po = $po->fresh();

        return $po;

    }


    public function testTemplateClientStatementGeneration()
    {

        
        foreach ($this->template_designs['statements'] as $template) {

            $invoice = $this->stubInvoice(CompanySettings::defaults());
    
            $design = \App\Factory\DesignFactory::create($invoice->company_id, $invoice->user_id);
            $design->name = Str::random(16);
            $dd = $design->design;
            $dd->body = file_get_contents(resource_path($template));
            $design->design = $dd;
            $design->save();

            $company = $invoice->company;
            $settings = $company->settings;
            $settings->statement_design_id = $design->hashed_id;
            $company->settings = $settings;
            $company->save();
            $invoice->company->settings = $settings;
            $invoice->push();

            $invoice = $invoice->service()->markPaid()->save();
            $invoice->load('invitations');

            $this->assertGreaterThan(0, $invoice->invitations()->count());

            $company = $company->fresh();
            $client = $invoice->client;
            $client = $client->load('invoices');


            $statement = (new \App\Services\Client\Statement($client, [
                            'start_date' => '1970-01-01',
                            'end_date' => '2045-01-01',
                            'show_payments_table' => true,
                            'show_aging_table' => true,
                            'show_credits_table' => true,
                            'template' => $design->hashed_id,
                            'status' => 'all',
                        ]));

            $pdf = $statement->run();

            $this->assertNotNull($pdf);

            \Illuminate\Support\Facades\Storage::put('/pdf/template_statements_' . basename($template). '.pdf', $pdf);

            $design->forceDelete();
            $company->forceDelete();
        }
        
    }

    public function testTemplatePaymentGeneration()
    {

        $invoice = $this->stubInvoice(CompanySettings::defaults());
        $invoice->service()->markPaid()->save();

        $payment = $invoice->fresh()->payments()->first();
        $payment->load('invoices');        

        $ts = new TemplateService();

        foreach ($this->template_designs['payments'] as $template) {

            $ts->setCompany($payment->company)
                ->setRawTemplate(file_get_contents(resource_path($template)))
                ->setEntity($payment)
                ->addGlobal(['currency_code' => 'EUR'])
                ->build(['payments' => collect([$payment])]);

            $pdf = $ts->getPdf();

            $this->assertNotNull($pdf);

            \Illuminate\Support\Facades\Storage::put('/pdf/template_payments_' . basename($template). '.pdf', $pdf);

        }
        
    }

    public function testTemplateDeliveryNoteGeneration()
    {
        $invoice = $this->stubInvoice(CompanySettings::defaults());

        $data = [
            'invoices' => collect($invoice),
        ];

        $ts = new TemplateService();

        foreach ($this->template_designs['delivery_notes'] as $template) {


            $pdf = $ts->setCompany($invoice->company)
            ->setRawTemplate(file_get_contents(resource_path($template)))
            ->build([
                'invoices' => collect([$this->invoice]),
            ])->getPdf();

            $this->assertNotNull($pdf);

            \Illuminate\Support\Facades\Storage::put('/pdf/template_delivery_note_' . basename($template). '.pdf', $pdf);

        }

    }

    public function testPurchaseOrderGeneration()
    {
        
        $settings = CompanySettings::defaults();
        $settings->pdf_variables = json_decode($this->max_pdf_variables);
        $settings->company_logo = 'https://pdf.invoicing.co/favicon-v2.png';
        $settings->website = 'www.invoiceninja.com';
        $settings->name = 'Invoice Ninja';
        $settings->address1 = 'Address 1';
        $settings->address2 = 'Address 2';
        $settings->city = 'City';
        $settings->state = 'State';
        $settings->postal_code = 'Postal Code';
        $settings->phone = '555-343-2323';
        $settings->email = $this->fake_email;
        $settings->country_id = '840';
        $settings->vat_number = 'vat number';
        $settings->id_number = 'id number';
        $settings->use_credits_payment = 'always';
        $settings->timezone_id = '1';
        $settings->entity_send_time = 0;
        $settings->hide_empty_columns_on_pdf = true;

        $po = $this->stubPurchaseOrder($settings, ['markdown_enabled' => true]);

        $items = $po->line_items;

        $first_item = $items[0];

        $first_item->notes = $this->faker->paragraphs(2, true);

        $items[] = $first_item;

        $new_item = $items[0];
        $new_item->notes = '**Bold** _Italic_ [Link](https://www.google.com)  
        + this  
        + and that  
        + is something to think about';

        $items[] = $new_item;

        $po->line_items = $items;
        $po->calc()->getPurchaseOrder();
        

        $this->assertGreaterThan(0, $po->invitations()->count());

        \App\Models\Design::where('is_custom', false)->cursor()->each(function ($design) use($po) {
            
            $po->design_id = $design->id;
            $po->save();
            $po = $po->fresh();

            $service = (new PdfService($po->invitations()->first(), 'purchase_order'))->boot();
            $pdf = $service->getPdf();

            $this->assertNotNull($pdf);

            \Illuminate\Support\Facades\Storage::put('/pdf/po_' . $design->name.'.pdf', $pdf);

        });

    }

    public function testMarkdownEnabled()
    {
        
        $settings = CompanySettings::defaults();
        $settings->pdf_variables = json_decode($this->max_pdf_variables);
        $settings->company_logo = 'https://pdf.invoicing.co/favicon-v2.png';
        $settings->website = 'www.invoiceninja.com';
        $settings->name = 'Invoice Ninja';
        $settings->address1 = 'Address 1';
        $settings->address2 = 'Address 2';
        $settings->city = 'City';
        $settings->state = 'State';
        $settings->postal_code = 'Postal Code';
        $settings->phone = '555-343-2323';
        $settings->email = $this->fake_email;
        $settings->country_id = '840';
        $settings->vat_number = 'vat number';
        $settings->id_number = 'id number';
        $settings->use_credits_payment = 'always';
        $settings->timezone_id = '1';
        $settings->entity_send_time = 0;
        $settings->hide_empty_columns_on_pdf = true;

        $invoice = $this->stubInvoice($settings, ['markdown_enabled' => true]);

        $items = $invoice->line_items;

        $first_item = $items[0];

        $first_item->notes = $this->faker->paragraphs(2, true);

        $items[] = $first_item;

        $new_item = $items[0];
        $new_item->notes = '**Bold** _Italic_ [Link](https://www.google.com)  
        + this  
        + and that  
        + is something to think about';

        $items[] = $new_item;

        $invoice->line_items = $items;
        $invoice->calc()->getInvoice();
        

        $this->assertGreaterThan(0, $invoice->invitations()->count());

        \App\Models\Design::where('is_custom', false)->cursor()->each(function ($design) use($invoice) {
            
            $invoice->design_id = $design->id;
            $invoice->save();
            $invoice = $invoice->fresh();

            $service = (new PdfService($invoice->invitations()->first()))->boot();
            $pdf = $service->getPdf();

            $this->assertNotNull($pdf);

            \Illuminate\Support\Facades\Storage::put('/pdf/markdown_' . $design->name.'.pdf', $pdf);

        });

    }



    public function testLargeDescriptionField()
    {
        
        $settings = CompanySettings::defaults();
        $settings->pdf_variables = json_decode($this->max_pdf_variables);
        $settings->company_logo = 'https://pdf.invoicing.co/favicon-v2.png';
        $settings->website = 'www.invoiceninja.com';
        $settings->name = 'Invoice Ninja';
        $settings->address1 = 'Address 1';
        $settings->address2 = 'Address 2';
        $settings->city = 'City';
        $settings->state = 'State';
        $settings->postal_code = 'Postal Code';
        $settings->phone = '555-343-2323';
        $settings->email = $this->fake_email;
        $settings->country_id = '840';
        $settings->vat_number = 'vat number';
        $settings->id_number = 'id number';
        $settings->use_credits_payment = 'always';
        $settings->timezone_id = '1';
        $settings->entity_send_time = 0;
        $settings->hide_empty_columns_on_pdf = true;

        $invoice = $this->stubInvoice($settings);

        $items = $invoice->line_items;

        $items[0]->notes = $this->faker->text(500);

        $invoice->line_items = $items;
        $invoice->save();

        $this->assertGreaterThan(0, $invoice->invitations()->count());

        \App\Models\Design::where('is_custom', false)->cursor()->each(function ($design) use($invoice) {
            
            $invoice->design_id = $design->id;
            $invoice->save();
            $invoice = $invoice->fresh();

            $service = (new PdfService($invoice->invitations()->first()))->boot();
            $pdf = $service->getPdf();

            $this->assertNotNull($pdf);

            \Illuminate\Support\Facades\Storage::put('/pdf/desc_' . $design->name.'.pdf', $pdf);

        });

    }



    public function testMaxInvoiceFields()
    {
        
        $settings = CompanySettings::defaults();
        $settings->pdf_variables = json_decode($this->max_pdf_variables);
        $settings->company_logo = 'https://pdf.invoicing.co/favicon-v2.png';
        $settings->website = 'www.invoiceninja.com';
        $settings->name = 'Invoice Ninja';
        $settings->address1 = 'Address 1';
        $settings->address2 = 'Address 2';
        $settings->city = 'City';
        $settings->state = 'State';
        $settings->postal_code = 'Postal Code';
        $settings->phone = '555-343-2323';
        $settings->email = $this->fake_email;
        $settings->country_id = '840';
        $settings->vat_number = 'vat number';
        $settings->id_number = 'id number';
        $settings->use_credits_payment = 'always';
        $settings->timezone_id = '1';
        $settings->entity_send_time = 0;
        $settings->hide_empty_columns_on_pdf = true;

        $invoice = $this->stubInvoice($settings);

        $this->assertGreaterThan(0, $invoice->invitations()->count());

        \App\Models\Design::where('is_custom', false)->cursor()->each(function ($design) use($invoice) {
            
            $invoice->design_id = $design->id;
            $invoice->save();
            $invoice = $invoice->fresh();

            $service = (new PdfService($invoice->invitations()->first()))->boot();
            $pdf = $service->getPdf();

            $this->assertNotNull($pdf);

            \Illuminate\Support\Facades\Storage::put('/pdf/max_fields_' . $design->name.'.pdf', $pdf);

        });

    }

    public function testMinInvoiceFields()
    {
        
        $settings = CompanySettings::defaults();
        $settings->pdf_variables = json_decode($this->min_pdf_variables);
        $settings->company_logo = 'https://pdf.invoicing.co/favicon-v2.png';
        $settings->website = 'www.invoiceninja.com';
        $settings->name = 'Invoice Ninja';
        $settings->address1 = 'Address 1';
        $settings->address2 = 'Address 2';
        $settings->city = 'City';
        $settings->state = 'State';
        $settings->postal_code = 'Postal Code';
        $settings->phone = '555-343-2323';
        $settings->email = $this->fake_email;
        $settings->country_id = '840';
        $settings->vat_number = 'vat number';
        $settings->id_number = 'id number';
        $settings->use_credits_payment = 'always';
        $settings->timezone_id = '1';
        $settings->entity_send_time = 0;
        $settings->hide_empty_columns_on_pdf = true;

        $invoice = $this->stubInvoice($settings);

        \App\Models\Design::where('is_custom', false)->cursor()->each(function ($design) use ($invoice) {

            $invoice->design_id = $design->id;
            $invoice->save();
            $invoice = $invoice->fresh();

            $service = (new PdfService($invoice->invitations->first()))->boot();
            $pdf = $service->getPdf();

            $this->assertNotNull($pdf);

            \Illuminate\Support\Facades\Storage::put('/pdf/min_fields_' . $design->name.'.pdf', $pdf);

        });

    }


    public function testStatementPdfGeneration()
    {

        $pdf = $this->client->service()->statement([
            'client_id' => $this->client->hashed_id,
            'start_date' => '2000-01-01',
            'end_date' => '2023-01-01',
            'show_aging_table' => true,
            'show_payments_table' => true,
            'status' => 'all'    
        ]);
    

        $this->assertNotNull($pdf);

        \Illuminate\Support\Facades\Storage::put('/pdf/statement.pdf', $pdf);


    }

    public function testMultiDesignGeneration()
    {

        if (config('ninja.testvars.travis')) {
            $this->markTestSkipped();
        }

        \App\Models\Design::where('is_custom',false)->cursor()->each(function ($design){

            $this->invoice->design_id = $design->id;
            $this->invoice->save();
            $this->invoice = $this->invoice->fresh();

            $invitation = $this->invoice->invitations->first();

            $service = (new PdfService($invitation))->boot();
            $pdf = $service->getPdf();

            $this->assertNotNull($pdf);

            \Illuminate\Support\Facades\Storage::put('/pdf/' . $design->name.'.pdf', $pdf);
            
        });
    
        \App\Models\Design::where('is_custom', false)->cursor()->each(function ($design) {


            $this->invoice->design_id = $design->id;
            $this->invoice->save();
            $this->invoice = $this->invoice->fresh();

            $invitation = $this->invoice->invitations->first();

            $service = (new PdfService($invitation, 'delivery_note'))->boot();
            $pdf = $service->getPdf();

            $this->assertNotNull($pdf);

            \Illuminate\Support\Facades\Storage::put('/pdf/dn_' . $design->name.'.pdf', $pdf);

        });

    }

    public function testPdfGeneration()
    {

        if(config('ninja.testvars.travis')) {
            $this->markTestSkipped();
        }

        $invitation = $this->invoice->invitations->first();

        $service = (new PdfService($invitation))->boot();

        $this->assertNotNull($service->getPdf());

    }

    public function testHtmlGeneration()
    {

        $invitation = $this->invoice->invitations->first();

        $service = (new PdfService($invitation))->boot();

        $this->assertIsString($service->getHtml());

    }

    public function testInitOfClass()
    {

        $invitation = $this->invoice->invitations->first();

        $service = (new PdfService($invitation))->boot();

        $this->assertInstanceOf(PdfService::class, $service);

    }

    public function testEntityResolution()
    {

        $invitation = $this->invoice->invitations->first();

        $service = (new PdfService($invitation))->boot();

        $this->assertInstanceOf(PdfConfiguration::class, $service->config);


    }

    public function testDefaultDesign()
    {
        $invitation = $this->invoice->invitations->first();

        $service = (new PdfService($invitation))->boot();

        $this->assertEquals(2, $service->config->design->id);

    }

    public function testHtmlIsArray()
    {
        $invitation = $this->invoice->invitations->first();

        $service = (new PdfService($invitation))->boot();

        $this->assertIsArray($service->html_variables);

    }

    public function testTemplateResolution()
    {
        $invitation = $this->invoice->invitations->first();

        $service = (new PdfService($invitation))->boot();

        $this->assertIsString($service->designer->template);

    }

}
