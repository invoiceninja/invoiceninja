<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2022. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Services\Pdf;

use App\DataMapper\ClientSettings;
use App\Models\Client;
use App\Models\Company;
use App\Models\Country;
use App\Models\Credit;
use App\Models\Currency;
use App\Models\Design;
use App\Models\Invoice;
use App\Models\InvoiceInvitation;
use App\Models\PurchaseOrder;
use App\Models\Quote;
use App\Models\Vendor;
use App\Utils\Traits\MakesHash;

class PdfMock
{
    use MakesHash;

    private mixed $mock;

    public object $settings;

    public function __construct(public array $request, public Company $company)
    {
    }

    public function getPdf(): mixed
    {
        $pdf_service = new PdfService($this->mock->invitation);

        $pdf_config = (new PdfConfiguration($pdf_service));
        $pdf_config->entity = $this->mock;
        $pdf_config->entity_string = $this->request['entity_type'];
        $pdf_config->setTaxMap($this->mock->tax_map);
        $pdf_config->setTotalTaxMap($this->mock->total_tax_map);
        $pdf_config->client = $this->mock->client;
        $pdf_config->settings_object = $this->mock->client;
        $pdf_config->settings = $this->getMergedSettings();
        $this->settings = $pdf_config->settings;
        $pdf_config->entity_design_id = $pdf_config->settings->{"{$pdf_config->entity_string}_design_id"};
        $pdf_config->setPdfVariables();
        $pdf_config->setCurrency(Currency::find($this->settings->currency_id));
        $pdf_config->setCountry(Country::find($this->settings->country_id));
        $pdf_config->design = Design::find($this->decodePrimaryKey($pdf_config->entity_design_id));
        $pdf_config->currency_entity = $this->mock->client;
        
        $pdf_service->config = $pdf_config;

        $pdf_designer = (new PdfDesigner($pdf_service))->build();
        $pdf_service->designer = $pdf_designer;

        $pdf_service->html_variables = $this->getStubVariables();

        $pdf_builder = (new PdfBuilder($pdf_service))->build();
        $pdf_service->builder = $pdf_builder;

        $html = $pdf_service->getHtml();

        nlog($html);

        return $pdf_service->resolvePdfEngine($html);
    }

    public function build(): self
    {
        $this->mock = $this->initEntity();

        return $this;
    }

    public function initEntity(): mixed
    {
        match ($this->request['entity_type']) {
            'invoice' => $entity = Invoice::factory()->make(),
            'quote' => $entity = Quote::factory()->make(),
            'credit' => $entity = Credit::factory()->make(),
            'purchase_order' => $entity = PurchaseOrder::factory()->make(),
            default => $entity = Invoice::factory()->make()
        };

        if ($this->request['entity_type'] == PurchaseOrder::class) {
            $entity->vendor = Vendor::factory()->make();
        } else {
            $entity->client = Client::factory()->make();
        }
    
        $entity->tax_map = $this->getTaxMap();
        $entity->total_tax_map = $this->getTotalTaxMap();
        $entity->invitation = InvoiceInvitation::factory()->make();
        $entity->invitation->company = $this->company;

        return $entity;
    }

    public function getMergedSettings() :object
    {
        match ($this->request['settings_type']) {
            'group' => $settings = ClientSettings::buildClientSettings($this->company->settings, $this->request['settings']),
            'client' => $settings = ClientSettings::buildClientSettings($this->company->settings, $this->request['settings']),
            'company' => $settings = (object)$this->request['settings'],
            default => $settings = $this->company->settings,
        };

        return $settings;
    }


    private function getTaxMap()
    {
        return collect([['name' => 'GST', 'total' => 10]]);
    }

    private function getTotalTaxMap()
    {
        return [['name' => 'GST', 'total' => 10]];
    }

    public function getStubVariables()
    {
        return ['values' =>
         [
    
    '$client.shipping_postal_code' => '46420',
    '$client.billing_postal_code' => '11243',
    '$company.city_state_postal' => 'Beveley Hills, CA, 90210',
    '$company.postal_city_state' => 'CA',
    '$company.postal_city' => '90210, CA',
    '$product.gross_line_total' => '100',
    '$client.postal_city_state' => '11243 Aufderharchester, North Carolina',
    '$client.postal_city' => '11243 Aufderharchester, North Carolina',
    '$client.shipping_address1' => '453',
    '$client.shipping_address2' => '66327 Waters Trail',
    '$client.city_state_postal' => 'Aufderharchester, North Carolina 11243',
    '$client.shipping_address' => '453<br/>66327 Waters Trail<br/>Aufderharchester, North Carolina 11243<br/>Afghanistan<br/>',
    '$client.billing_address2' => '63993 Aiyana View',
    '$client.billing_address1' => '8447',
    '$client.shipping_country' => 'USA',
    '$invoiceninja.whitelabel' => 'https://raw.githubusercontent.com/invoiceninja/invoiceninja/v5-develop/public/images/new_logo.png',
    '$client.billing_address' => '8447<br/>63993 Aiyana View<br/>Aufderharchester, North Carolina 11243<br/>Afghanistan<br/>',
    '$client.billing_country' => 'USA',
    '$task.gross_line_total' => '100',
    '$contact.portal_button' => '<a class="button" href="http://ninja.test:8000/client/key_login/zJJEjlUtXPiNnnnyO2tcYia64PSwauidy61eDnMU?client_hash=nzikYQITs1kyUK61GScTNW67JwhTRkOBVdvsHzIv">View client portal</a>',
    '$client.shipping_state' => 'Delaware',
    '$invoice.public_notes' => 'These are some public notes for your document',
    '$client.shipping_city' => 'Kesslerport',
    '$client.billing_state' => 'North Carolina',
    '$product.description' => 'A Product Description',
    '$product.product_key' => 'A Product Key',
    '$entity.public_notes' => 'Entity Public notes',
    '$invoice.balance_due' => '$0.00',
    '$client.public_notes' => '&nbsp;',
    '$company.postal_code' => $this->settings->postal_code,
    '$client.billing_city' => 'Aufderharchester',
    '$secondary_font_name' => $this->settings->primary_font,
    '$product.line_total' => '',
    '$product.tax_amount' => '',
    '$company.vat_number' => $this->settings->vat_number,
    '$invoice.invoice_no' => '0029',
    '$quote.quote_number' => '0029',
    '$client.postal_code' => '11243',
    '$contact.first_name' => 'Benedict',
    '$secondary_font_url' => 'https://fonts.googleapis.com/css2?family=Roboto&display=swap',
    '$contact.signature' => '',
    '$company_logo_size' => $this->settings->company_logo_size ?: '65%',
    '$product.tax_name1' => '',
    '$product.tax_name2' => '',
    '$product.tax_name3' => '',
    '$product.unit_cost' => '',
    '$quote.valid_until' => '2023-10-24',
    '$custom_surcharge1' => '$0.00',
    '$custom_surcharge2' => '$0.00',
    '$custom_surcharge3' => '$0.00',
    '$custom_surcharge4' => '$0.00',
    '$quote.balance_due' => '$0.00',
    '$company.id_number' => $this->settings->id_number,
    '$invoice.po_number' => '&nbsp;',
    '$invoice_total_raw' => 0.0,
    '$postal_city_state' => '11243 Aufderharchester, North Carolina',
    '$client.vat_number' => '975977515',
    '$city_state_postal' => 'Aufderharchester, North Carolina 11243',
    '$contact.full_name' => 'Benedict Eichmann',
    '$contact.last_name' => 'Eichmann',
    '$company.country_2' => 'US',
    '$product.product1' => '',
    '$product.product2' => '',
    '$product.product3' => '',
    '$product.product4' => '',
    '$statement_amount' => '',
    '$task.description' => '',
    '$product.discount' => '',
    '$entity_issued_to' => 'Bob JOnes',
    '$assigned_to_user' => '',
    '$product.quantity' => '',
    '$total_tax_labels' => '',
    '$total_tax_values' => '',
    '$invoice.discount' => '$0.00',
    '$invoice.subtotal' => '$0.00',
    '$company.address2' => $this->settings->address2,
    '$partial_due_date' => '&nbsp;',
    '$invoice.due_date' => '&nbsp;',
    '$client.id_number' => '&nbsp;',
    '$credit.po_number' => '&nbsp;',
    '$company.address1' => $this->settings->address1,
    '$credit.credit_no' => '0029',
    '$invoice.datetime' => '25/Feb/2023 1:10 am',
    '$contact.custom1' => null,
    '$contact.custom2' => null,
    '$contact.custom3' => null,
    '$contact.custom4' => null,
    '$task.line_total' => '',
    '$line_tax_labels' => '',
    '$line_tax_values' => '',
    '$secondary_color' => $this->settings->secondary_color,
    '$invoice.balance' => '$0.00',
    '$invoice.custom1' => '&nbsp;',
    '$invoice.custom2' => '&nbsp;',
    '$invoice.custom3' => '&nbsp;',
    '$invoice.custom4' => '&nbsp;',
    '$company.custom1' => '&nbsp;',
    '$company.custom2' => '&nbsp;',
    '$company.custom3' => '&nbsp;',
    '$company.custom4' => '&nbsp;',
    '$quote.po_number' => '&nbsp;',
    '$company.website' => $this->settings->website,
    '$balance_due_raw' => '0.00',
    '$entity.datetime' => '25/Feb/2023 1:10 am',
    '$credit.datetime' => '25/Feb/2023 1:10 am',
    '$client.address2' => '63993 Aiyana View',
    '$client.address1' => '8447',
    '$user.first_name' => 'Derrick Monahan DDS',
    '$created_by_user' => 'Derrick Monahan DDS Erna Wunsch',
    '$client.currency' => 'USD',
    '$company.country' => 'United States',
    '$company.address' => 'United States<br/>',
    '$tech_hero_image' => 'http://ninja.test:8000/images/pdf-designs/tech-hero-image.jpg',
    '$task.tax_name1' => '',
    '$task.tax_name2' => '',
    '$task.tax_name3' => '',
    '$client.balance' => '$0.00',
    '$client_balance' => '$0.00',
    '$credit.balance' => '$0.00',
    '$credit_balance' => '$0.00',
    '$gross_subtotal' => '$0.00',
    '$invoice.amount' => '$0.00',
    '$client.custom1' => '&nbsp;',
    '$client.custom2' => '&nbsp;',
    '$client.custom3' => '&nbsp;',
    '$client.custom4' => '&nbsp;',
    '$emailSignature' => '&nbsp;',
    '$invoice.number' => '0029',
    '$quote.quote_no' => '0029',
    '$quote.datetime' => '25/Feb/2023 1:10 am',
    '$client_address' => '8447<br/>63993 Aiyana View<br/>Aufderharchester, North Carolina 11243<br/>Afghanistan<br/>',
    '$client.address' => '8447<br/>63993 Aiyana View<br/>Aufderharchester, North Carolina 11243<br/>Afghanistan<br/>',
    '$payment_button' => '<a class="button" href="http://ninja.test:8000/client/pay/UAUY8vIPuno72igmXbbpldwo5BDDKIqs">Pay Now</a>',
    '$payment_qrcode' => '<svg class=\'pqrcode\' viewBox=\'0 0 200 200\' width=\'200\' height=\'200\' x=\'0\' y=\'0\' xmlns=\'http://www.w3.org/2000/svg\'>
          <rect x=\'0\' y=\'0\' width=\'100%\'\' height=\'100%\' /><?xml version="1.0" encoding="UTF-8"?>
<svg xmlns="http://www.w3.org/2000/svg" version="1.1" width="200" height="200" viewBox="0 0 200 200"><rect x="0" y="0" width="200" height="200" fill="#fefefe"/><g transform="scale(4.878)"><g transform="translate(4,4)"><path fill-rule="evenodd" d="M9 0L9 1L8 1L8 2L9 2L9 3L8 3L8 4L10 4L10 7L11 7L11 4L12 4L12 5L13 5L13 4L12 4L12 2L14 2L14 7L15 7L15 6L16 6L16 8L15 8L15 10L14 10L14 11L16 11L16 12L14 12L14 13L15 13L15 14L14 14L14 15L15 15L15 14L17 14L17 15L16 15L16 16L14 16L14 17L15 17L15 18L14 18L14 19L13 19L13 18L11 18L11 15L8 15L8 12L9 12L9 13L10 13L10 14L11 14L11 13L12 13L12 14L13 14L13 13L12 13L12 11L13 11L13 10L11 10L11 11L10 11L10 9L11 9L11 8L6 8L6 9L5 9L5 8L0 8L0 10L1 10L1 12L2 12L2 11L3 11L3 10L4 10L4 11L5 11L5 12L3 12L3 13L7 13L7 14L6 14L6 15L5 15L5 14L1 14L1 15L0 15L0 19L1 19L1 20L0 20L0 25L1 25L1 20L2 20L2 19L3 19L3 20L4 20L4 21L5 21L5 20L6 20L6 21L8 21L8 23L7 23L7 22L5 22L5 24L4 24L4 25L8 25L8 27L10 27L10 28L11 28L11 29L9 29L9 28L8 28L8 33L9 33L9 30L11 30L11 29L12 29L12 32L13 32L13 33L14 33L14 32L15 32L15 33L17 33L17 32L19 32L19 31L18 31L18 30L16 30L16 28L17 28L17 29L18 29L18 28L19 28L19 27L18 27L18 26L17 26L17 27L16 27L16 26L15 26L15 25L16 25L16 24L18 24L18 25L19 25L19 23L18 23L18 22L19 22L19 20L17 20L17 19L20 19L20 25L21 25L21 26L22 26L22 28L21 28L21 27L20 27L20 33L21 33L21 30L24 30L24 32L25 32L25 33L27 33L27 32L29 32L29 33L32 33L32 32L33 32L33 31L31 31L31 32L29 32L29 30L32 30L32 29L33 29L33 27L32 27L32 26L31 26L31 25L32 25L32 24L31 24L31 25L30 25L30 23L29 23L29 21L30 21L30 22L31 22L31 21L32 21L32 22L33 22L33 21L32 21L32 20L33 20L33 18L32 18L32 20L31 20L31 21L30 21L30 19L29 19L29 18L28 18L28 17L25 17L25 16L28 16L28 15L30 15L30 14L31 14L31 17L30 17L30 18L31 18L31 17L32 17L32 16L33 16L33 15L32 15L32 14L31 14L31 13L32 13L32 12L33 12L33 11L32 11L32 10L31 10L31 9L32 9L32 8L31 8L31 9L30 9L30 8L29 8L29 10L28 10L28 11L30 11L30 14L29 14L29 12L27 12L27 11L26 11L26 10L25 10L25 9L26 9L26 8L25 8L25 9L23 9L23 8L24 8L24 7L25 7L25 5L23 5L23 3L24 3L24 4L25 4L25 3L24 3L24 2L25 2L25 0L24 0L24 1L23 1L23 0L21 0L21 1L20 1L20 4L21 4L21 5L22 5L22 7L23 7L23 8L22 8L22 9L18 9L18 8L19 8L19 6L20 6L20 8L21 8L21 6L20 6L20 5L19 5L19 6L18 6L18 5L17 5L17 2L18 2L18 1L19 1L19 0L18 0L18 1L17 1L17 0L16 0L16 1L17 1L17 2L16 2L16 3L15 3L15 2L14 2L14 1L15 1L15 0L14 0L14 1L11 1L11 2L10 2L10 0ZM21 1L21 2L22 2L22 3L23 3L23 2L22 2L22 1ZM10 3L10 4L11 4L11 3ZM15 4L15 5L16 5L16 4ZM8 5L8 7L9 7L9 5ZM12 6L12 9L14 9L14 8L13 8L13 6ZM17 6L17 7L18 7L18 6ZM23 6L23 7L24 7L24 6ZM16 8L16 9L17 9L17 10L16 10L16 11L17 11L17 10L18 10L18 11L20 11L20 10L18 10L18 9L17 9L17 8ZM27 8L27 9L28 9L28 8ZM1 9L1 10L2 10L2 9ZM4 9L4 10L5 10L5 11L6 11L6 12L7 12L7 11L9 11L9 10L8 10L8 9L6 9L6 10L5 10L5 9ZM22 9L22 10L21 10L21 11L22 11L22 10L23 10L23 11L24 11L24 12L23 12L23 13L22 13L22 14L21 14L21 12L18 12L18 13L17 13L17 12L16 12L16 13L17 13L17 14L21 14L21 15L20 15L20 16L19 16L19 15L17 15L17 16L16 16L16 18L21 18L21 19L22 19L22 18L21 18L21 17L22 17L22 16L23 16L23 19L25 19L25 18L24 18L24 16L23 16L23 13L24 13L24 14L25 14L25 12L26 12L26 15L27 15L27 14L28 14L28 13L27 13L27 12L26 12L26 11L24 11L24 10L23 10L23 9ZM6 10L6 11L7 11L7 10ZM30 10L30 11L31 11L31 10ZM10 12L10 13L11 13L11 12ZM1 15L1 17L2 17L2 18L1 18L1 19L2 19L2 18L3 18L3 19L4 19L4 20L5 20L5 19L6 19L6 20L8 20L8 21L10 21L10 23L8 23L8 24L10 24L10 27L11 27L11 26L14 26L14 25L15 25L15 24L16 24L16 23L17 23L17 22L18 22L18 21L17 21L17 20L16 20L16 19L14 19L14 21L13 21L13 19L12 19L12 21L10 21L10 20L11 20L11 18L10 18L10 17L8 17L8 15L6 15L6 16L7 16L7 17L5 17L5 16L4 16L4 15ZM12 15L12 17L13 17L13 15ZM3 16L3 18L4 18L4 19L5 19L5 17L4 17L4 16ZM17 16L17 17L18 17L18 16ZM20 16L20 17L21 17L21 16ZM6 18L6 19L7 19L7 18ZM8 18L8 20L9 20L9 19L10 19L10 18ZM26 18L26 19L27 19L27 20L26 20L26 21L25 21L25 22L24 22L24 20L22 20L22 22L21 22L21 23L22 23L22 25L23 25L23 28L22 28L22 29L24 29L24 30L25 30L25 32L27 32L27 31L28 31L28 30L27 30L27 31L26 31L26 29L24 29L24 24L23 24L23 23L27 23L27 24L29 24L29 23L27 23L27 20L29 20L29 19L27 19L27 18ZM15 20L15 21L14 21L14 23L12 23L12 25L13 25L13 24L14 24L14 23L16 23L16 22L15 22L15 21L16 21L16 20ZM2 21L2 22L3 22L3 23L4 23L4 22L3 22L3 21ZM12 21L12 22L13 22L13 21ZM22 22L22 23L23 23L23 22ZM6 23L6 24L7 24L7 23ZM10 23L10 24L11 24L11 23ZM2 24L2 25L3 25L3 24ZM25 25L25 28L28 28L28 25ZM26 26L26 27L27 27L27 26ZM29 26L29 27L30 27L30 28L29 28L29 29L32 29L32 27L31 27L31 26ZM12 27L12 28L13 28L13 30L14 30L14 29L15 29L15 28L16 28L16 27L15 27L15 28L14 28L14 27ZM17 27L17 28L18 28L18 27ZM15 30L15 31L16 31L16 30ZM10 31L10 32L11 32L11 31ZM13 31L13 32L14 32L14 31ZM22 32L22 33L23 33L23 32ZM0 0L0 7L7 7L7 0ZM1 1L1 6L6 6L6 1ZM2 2L2 5L5 5L5 2ZM26 0L26 7L33 7L33 0ZM27 1L27 6L32 6L32 1ZM28 2L28 5L31 5L31 2ZM0 26L0 33L7 33L7 26ZM1 27L1 32L6 32L6 27ZM2 28L2 31L5 31L5 28Z" fill="#000000"/></g></g></svg>
</svg>',
    '$client.country' => 'Afghanistan',
    '$user.last_name' => 'Erna Wunsch',
    '$client.website' => 'http://www.parisian.org/',
    '$dir_text_align' => 'left',
    '$entity_images' => '',
    '$task.discount' => '',
    '$contact.email' => '',
    '$primary_color' => $this->settings->primary_color,
    '$credit_amount' => '$0.00',
    '$invoice.total' => '$0.00',
    '$invoice.taxes' => '$0.00',
    '$quote.custom1' => '&nbsp;',
    '$quote.custom2' => '&nbsp;',
    '$quote.custom3' => '&nbsp;',
    '$quote.custom4' => '&nbsp;',
    '$company.email' => $this->settings->email,
    '$client.number' => '12345',
    '$company.phone' => $this->settings->phone,
    '$company.state' => $this->settings->state,
    '$credit.number' => '0029',
    '$entity_number' => '0029',
    '$credit_number' => '0029',
    '$global_margin' => '6.35mm',
    '$contact.phone' => '681-480-9828',
    '$portal_button' => '<a class="button" href="http://ninja.test:8000/client/key_login/zJJEjlUtXPiNnnnyO2tcYia64PSwauidy61eDnMU?client_hash=nzikYQITs1kyUK61GScTNW67JwhTRkOBVdvsHzIv">View client portal</a>',
    '$paymentButton' => '<a class="button" href="http://ninja.test:8000/client/pay/UAUY8vIPuno72igmXbbpldwo5BDDKIqs">Pay Now</a>',
    '$entity_footer' => 'Default invoice footer',
    '$client.lang_2' => 'en',
    '$product.date' => '',
    '$client.email' => 'client@gmail.com',
    '$product.item' => '',
    '$public_notes' => 'These are very public notes',
    '$task.service' => '',
    '$credit.total' => '$0.00',
    '$net_subtotal' => '$0.00',
    '$paid_to_date' => '$0.00',
    '$quote.amount' => '$0.00',
    '$company.city' => $this->settings->city,
    '$payment.date' => '&nbsp;',
    '$client.phone' => '555-123-3212',
    '$number_short' => '0029',
    '$quote.number' => '0029',
    '$invoice.date' => '25/Feb/2023',
    '$company.name' => $this->settings->name,
    '$portalButton' => '<a class="button" href="http://ninja.test:8000/client/key_login/zJJEjlUtXPiNnnnyO2tcYia64PSwauidy61eDnMU?client_hash=nzikYQITs1kyUK61GScTNW67JwhTRkOBVdvsHzIv">View client portal</a>',
    '$contact.name' => 'Benedict Eichmann',
    '$entity.terms' => 'Default company invoice terms',
    '$client.state' => 'North Carolina',
    '$company.logo' => $this->settings->company_logo,
    '$company_logo' => $this->settings->company_logo,
    '$payment_link' => 'http://ninja.test:8000/client/pay/UAUY8vIPuno72igmXbbpldwo5BDDKIqs',
    '$status_logo' => '',
    '$description' => '',
    '$product.tax' => '',
    '$valid_until' => '',
    '$your_entity' => '',
    '$shipping' => '',
    '$balance_due' => '$0.00',
    '$outstanding' => '$0.00',
    '$partial_due' => '$0.00',
    '$quote.total' => '$0.00',
    '$payment_due' => '&nbsp;',
    '$credit.date' => '25/Feb/2023',
    '$invoiceDate' => '25/Feb/2023',
    '$view_button' => '<a class="button" href="http://ninja.test:8000/client/invoice/UAUY8vIPuno72igmXbbpldwo5BDDKIqs">View Invoice</a>',
    '$client.city' => 'Aufderharchester',
    '$spc_qr_code' => 'SPC
0200
1

K
434343

 


CH







0.000000
USD







NON

0029
EPD
',
    '$client_name' => 'A Client Called Bob',
    '$client.name' => 'A Client Called Bob',
    '$paymentLink' => 'http://ninja.test:8000/client/pay/UAUY8vIPuno72igmXbbpldwo5BDDKIqs',
    '$payment_url' => 'http://ninja.test:8000/client/pay/UAUY8vIPuno72igmXbbpldwo5BDDKIqs',
    '$page_layout' => $this->settings->page_layout,
    '$task.task1' => '',
    '$task.task2' => '',
    '$task.task3' => '',
    '$task.task4' => '',
    '$task.hours' => '',
    '$amount_due' => '$0.00',
    '$amount_raw' => '0.00',
    '$invoice_no' => '0029',
    '$quote.date' => '25/Feb/2023',
    '$vat_number' => '975977515',
    '$viewButton' => '<a class="button" href="http://ninja.test:8000/client/invoice/UAUY8vIPuno72igmXbbpldwo5BDDKIqs">View Invoice</a>',
    '$portal_url' => 'http://ninja.test:8000/client/',
    '$task.date' => '',
    '$task.rate' => '',
    '$task.cost' => '',
    '$statement' => '',
    '$user_iban' => '&nbsp;',
    '$signature' => '&nbsp;',
    '$id_number' => '&nbsp;',
    '$credit_no' => '0029',
    '$font_size' => $this->settings->font_size,
    '$view_link' => '<a class="button" href="http://ninja.test:8000/client/invoice/UAUY8vIPuno72igmXbbpldwo5BDDKIqs">View Invoice</a>',
    '$page_size' => $this->settings->page_size,
    '$country_2' => 'AF',
    '$firstName' => 'Benedict',
    '$user.name' => 'Derrick Monahan DDS Erna Wunsch',
    '$font_name' => 'Roboto',
    '$auto_bill' => 'This invoice will automatically be billed to your credit card on file on the due date.',
    '$payments' => '',
    '$task.tax' => '',
    '$discount' => '$0.00',
    '$subtotal' => '$0.00',
    '$company1' => '&nbsp;',
    '$company2' => '&nbsp;',
    '$company3' => '&nbsp;',
    '$company4' => '&nbsp;',
    '$due_date' => '&nbsp;',
    '$poNumber' => '&nbsp;',
    '$quote_no' => '0029',
    '$address2' => '63993 Aiyana View',
    '$address1' => '8447',
    '$viewLink' => '<a class="button" href="http://ninja.test:8000/client/invoice/UAUY8vIPuno72igmXbbpldwo5BDDKIqs">View Invoice</a>',
    '$autoBill' => 'This invoice will automatically be billed to your credit card on file on the due date.',
    '$view_url' => 'http://ninja.test:8000/client/invoice/UAUY8vIPuno72igmXbbpldwo5BDDKIqs',
    '$font_url' => 'https://fonts.googleapis.com/css2?family=Roboto&display=swap',
    '$details' => '',
    '$balance' => '$0.00',
    '$partial' => '$0.00',
    '$client1' => '&nbsp;',
    '$client2' => '&nbsp;',
    '$client3' => '&nbsp;',
    '$client4' => '&nbsp;',
    '$dueDate' => '&nbsp;',
    '$invoice' => '0029',
    '$account' => '434343',
    '$country' => 'Afghanistan',
    '$contact' => 'Benedict Eichmann',
    '$app_url' => 'http://ninja.test:8000',
    '$website' => 'http://www.parisian.org/',
    '$entity' => '',
    '$thanks' => '',
    '$amount' => '$0.00',
    '$method' => '&nbsp;',
    '$number' => '0029',
    '$footer' => 'Default invoice footer',
    '$client' => 'cypress',
    '$email' => 'email@invoiceninja.net',
    '$notes' => '',
    '_rate1' => '',
    '_rate2' => '',
    '_rate3' => '',
    '$taxes' => '$0.00',
    '$total' => '$0.00',
    '$phone' => '&nbsp;',
    '$terms' => 'Default company invoice terms',
    '$from' => 'Bob Jones',
    '$item' => '',
    '$date' => '25/Feb/2023',
    '$tax' => '',
    '$dir' => 'ltr',
    '$to' => 'Jimmy Giggles',
    '$show_paid_stamp' => $this->settings->show_paid_stamp ? 'flex' : 'none',
    '$status_logo' => '<div class="stamp is-paid"> ' . ctrans('texts.paid') .'</div>',
    '$show_shipping_address' => $this->settings->show_shipping_address ? 'flex' : 'none',
    '$show_shipping_address_block' => $this->settings->show_shipping_address ? 'block' : 'none',
    '$show_shipping_address_visibility' => $this->settings->show_shipping_address ? 'visible' : 'hidden',
  ],
  'labels' =>
  [
    '$client.shipping_postal_code_label' => 'Shipping Postal Code',
    '$client.billing_postal_code_label' => 'Postal Code',
    '$company.city_state_postal_label' => 'City/State/Postal',
    '$company.postal_city_state_label' => 'Postal/City/State',
    '$company.postal_city_label' => 'Postal/City',
    '$product.gross_line_total_label' => 'Gross line total',
    '$client.postal_city_state_label' => 'Postal/City/State',
    '$client.postal_city_label' => 'Postal/City',
    '$client.shipping_address1_label' => 'Shipping Street',
    '$client.shipping_address2_label' => 'Shipping Apt/Suite',
    '$client.city_state_postal_label' => 'City/State/Postal',
    '$client.shipping_address_label' => 'Shipping Address',
    '$client.billing_address2_label' => 'Apt/Suite',
    '$client.billing_address1_label' => 'Street',
    '$client.shipping_country_label' => 'Shipping Country',
    '$invoiceninja.whitelabel_label' => '',
    '$client.billing_address_label' => 'Address',
    '$client.billing_country_label' => 'Country',
    '$task.gross_line_total_label' => 'Gross line total',
    '$contact.portal_button_label' => 'view_client_portal',
    '$client.shipping_state_label' => 'Shipping State/Province',
    '$invoice.public_notes_label' => 'Public Notes',
    '$client.shipping_city_label' => 'Shipping City',
    '$client.billing_state_label' => 'State/Province',
    '$product.description_label' => 'Description',
    '$product.product_key_label' => 'Product',
    '$entity.public_notes_label' => 'Public Notes',
    '$invoice.balance_due_label' => 'Balance Due',
    '$client.public_notes_label' => 'Notes',
    '$company.postal_code_label' => 'Postal Code',
    '$client.billing_city_label' => 'City',
    '$secondary_font_name_label' => '',
    '$product.line_total_label' => 'Line Total',
    '$product.tax_amount_label' => 'Tax',
    '$company.vat_number_label' => 'VAT Number',
    '$invoice.invoice_no_label' => 'Invoice Number',
    '$quote.quote_number_label' => 'Quote Number',
    '$client.postal_code_label' => 'Postal Code',
    '$contact.first_name_label' => 'First Name',
    '$secondary_font_url_label' => '',
    '$contact.signature_label' => '',
    '$company_logo_size_label' => '',
    '$product.tax_name1_label' => 'Tax',
    '$product.tax_name2_label' => 'Tax',
    '$product.tax_name3_label' => 'Tax',
    '$product.unit_cost_label' => 'Unit Cost',
    '$quote.valid_until_label' => 'Valid Until',
    '$custom_surcharge1_label' => '',
    '$custom_surcharge2_label' => '',
    '$custom_surcharge3_label' => '',
    '$custom_surcharge4_label' => '',
    '$quote.balance_due_label' => 'Balance Due',
    '$company.id_number_label' => 'ID Number',
    '$invoice.po_number_label' => 'PO Number',
    '$invoice_total_raw_label' => 'Invoice Total',
    '$postal_city_state_label' => 'Postal/City/State',
    '$client.vat_number_label' => 'VAT Number',
    '$city_state_postal_label' => 'City/State/Postal',
    '$contact.full_name_label' => 'Name',
    '$contact.last_name_label' => 'Last Name',
    '$company.country_2_label' => 'Country',
    '$product.product1_label' => '',
    '$product.product2_label' => '',
    '$product.product3_label' => '',
    '$product.product4_label' => '',
    '$statement_amount_label' => 'Amount',
    '$task.description_label' => 'Description',
    '$product.discount_label' => 'Discount',
    '$entity_issued_to_label' => 'Invoice issued to',
    '$assigned_to_user_label' => 'Name',
    '$product.quantity_label' => 'Quantity',
    '$total_tax_labels_label' => 'Taxes',
    '$total_tax_values_label' => 'Taxes',
    '$invoice.discount_label' => 'Discount',
    '$invoice.subtotal_label' => 'Subtotal',
    '$company.address2_label' => 'Apt/Suite',
    '$partial_due_date_label' => 'Due Date',
    '$invoice.due_date_label' => 'Due Date',
    '$client.id_number_label' => 'ID Number',
    '$credit.po_number_label' => 'PO Number',
    '$company.address1_label' => 'Street',
    '$credit.credit_no_label' => 'Invoice Number',
    '$invoice.datetime_label' => 'Date',
    '$contact.custom1_label' => '',
    '$contact.custom2_label' => '',
    '$contact.custom3_label' => '',
    '$contact.custom4_label' => '',
    '$task.line_total_label' => 'Line Total',
    '$line_tax_labels_label' => 'Taxes',
    '$line_tax_values_label' => 'Taxes',
    '$secondary_color_label' => '',
    '$invoice.balance_label' => 'Balance',
    '$invoice.custom1_label' => '',
    '$invoice.custom2_label' => '',
    '$invoice.custom3_label' => '',
    '$invoice.custom4_label' => '',
    '$company.custom1_label' => '',
    '$company.custom2_label' => '',
    '$company.custom3_label' => '',
    '$company.custom4_label' => '',
    '$quote.po_number_label' => 'PO Number',
    '$company.website_label' => 'Website',
    '$balance_due_raw_label' => 'Balance Due',
    '$entity.datetime_label' => 'Date',
    '$credit.datetime_label' => 'Date',
    '$client.address2_label' => 'Apt/Suite',
    '$client.address1_label' => 'Street',
    '$user.first_name_label' => 'First Name',
    '$created_by_user_label' => 'Name',
    '$client.currency_label' => '',
    '$company.country_label' => 'Country',
    '$company.address_label' => 'Address',
    '$tech_hero_image_label' => '',
    '$task.tax_name1_label' => 'Tax',
    '$task.tax_name2_label' => 'Tax',
    '$task.tax_name3_label' => 'Tax',
    '$client.balance_label' => 'Account balance',
    '$client_balance_label' => 'Account balance',
    '$credit.balance_label' => 'Balance',
    '$credit_balance_label' => 'Credit Balance',
    '$gross_subtotal_label' => 'Subtotal',
    '$invoice.amount_label' => 'Total',
    '$client.custom1_label' => '',
    '$client.custom2_label' => '',
    '$client.custom3_label' => '',
    '$client.custom4_label' => '',
    '$emailSignature_label' => '',
    '$invoice.number_label' => 'Invoice Number',
    '$quote.quote_no_label' => 'Quote Number',
    '$quote.datetime_label' => 'Date',
    '$client_address_label' => 'Address',
    '$client.address_label' => 'Address',
    '$payment_button_label' => 'Pay Now',
    '$payment_qrcode_label' => 'Pay Now',
    '$client.country_label' => 'Country',
    '$user.last_name_label' => 'Last Name',
    '$client.website_label' => 'Website',
    '$dir_text_align_label' => '',
    '$entity_images_label' => '',
    '$task.discount_label' => 'Discount',
    '$contact.email_label' => 'Email',
    '$primary_color_label' => '',
    '$credit_amount_label' => 'Credit Amount',
    '$invoice.total_label' => 'Invoice Total',
    '$invoice.taxes_label' => 'Taxes',
    '$quote.custom1_label' => '',
    '$quote.custom2_label' => '',
    '$quote.custom3_label' => '',
    '$quote.custom4_label' => '',
    '$company.email_label' => 'Email',
    '$client.number_label' => 'Number',
    '$company.phone_label' => 'Phone',
    '$company.state_label' => 'State/Province',
    '$credit.number_label' => 'Credit Number',
    '$entity_number_label' => 'Invoice Number',
    '$credit_number_label' => 'Invoice Number',
    '$global_margin_label' => '',
    '$contact.phone_label' => 'Phone',
    '$portal_button_label' => 'view_client_portal',
    '$paymentButton_label' => 'Pay Now',
    '$entity_footer_label' => '',
    '$client.lang_2_label' => '',
    '$product.date_label' => 'Date',
    '$client.email_label' => 'Email',
    '$product.item_label' => 'Item',
    '$public_notes_label' => 'Public Notes',
    '$task.service_label' => 'Service',
    '$credit.total_label' => 'Credit Total',
    '$net_subtotal_label' => 'Net',
    '$paid_to_date_label' => 'Paid to Date',
    '$quote.amount_label' => 'Quote Total',
    '$company.city_label' => 'City',
    '$payment.date_label' => 'Payment Date',
    '$client.phone_label' => 'Phone',
    '$number_short_label' => 'Invoice #',
    '$quote.number_label' => 'Quote Number',
    '$invoice.date_label' => 'Invoice Date',
    '$company.name_label' => 'Company Name',
    '$portalButton_label' => 'view_client_portal',
    '$contact.name_label' => 'Contact Name',
    '$entity.terms_label' => 'Invoice Terms',
    '$client.state_label' => 'State/Province',
    '$company.logo_label' => 'Logo',
    '$company_logo_label' => 'Logo',
    '$payment_link_label' => 'Pay Now',
    '$status_logo_label' => '',
    '$description_label' => 'Description',
    '$product.tax_label' => 'Tax',
    '$valid_until_label' => 'Valid Until',
    '$your_entity_label' => 'Your Invoice',
    '$shipping_label' => 'Shipping',
    '$balance_due_label' => 'Balance Due',
    '$outstanding_label' => 'Balance Due',
    '$partial_due_label' => 'Partial Due',
    '$quote.total_label' => 'Total',
    '$payment_due_label' => 'Payment due',
    '$credit.date_label' => 'Credit Date',
    '$invoiceDate_label' => 'Invoice Date',
    '$view_button_label' => 'View Invoice',
    '$client.city_label' => 'City',
    '$spc_qr_code_label' => '',
    '$client_name_label' => 'Client Name',
    '$client.name_label' => 'Client Name',
    '$paymentLink_label' => 'Pay Now',
    '$payment_url_label' => 'Pay Now',
    '$page_layout_label' => '',
    '$task.task1_label' => '',
    '$task.task2_label' => '',
    '$task.task3_label' => '',
    '$task.task4_label' => '',
    '$task.hours_label' => 'Hours',
    '$amount_due_label' => 'Amount due',
    '$amount_raw_label' => 'Amount',
    '$invoice_no_label' => 'Invoice Number',
    '$quote.date_label' => 'Quote Date',
    '$vat_number_label' => 'VAT Number',
    '$viewButton_label' => 'View Invoice',
    '$portal_url_label' => '',
    '$task.date_label' => 'Date',
    '$task.rate_label' => 'Rate',
    '$task.cost_label' => 'Rate',
    '$statement_label' => 'Statement',
    '$user_iban_label' => '',
    '$signature_label' => '',
    '$id_number_label' => 'ID Number',
    '$credit_no_label' => 'Invoice Number',
    '$font_size_label' => '',
    '$view_link_label' => 'View Invoice',
    '$page_size_label' => '',
    '$country_2_label' => 'Country',
    '$firstName_label' => 'First Name',
    '$user.name_label' => 'Name',
    '$font_name_label' => '',
    '$auto_bill_label' => '',
    '$payments_label' => 'Payments',
    '$task.tax_label' => 'Tax',
    '$discount_label' => 'Discount',
    '$subtotal_label' => 'Subtotal',
    '$company1_label' => '',
    '$company2_label' => '',
    '$company3_label' => '',
    '$company4_label' => '',
    '$due_date_label' => 'Due Date',
    '$poNumber_label' => 'PO Number',
    '$quote_no_label' => 'Quote Number',
    '$address2_label' => 'Apt/Suite',
    '$address1_label' => 'Street',
    '$viewLink_label' => 'View Invoice',
    '$autoBill_label' => '',
    '$view_url_label' => 'View Invoice',
    '$font_url_label' => '',
    '$details_label' => 'Details',
    '$balance_label' => 'Balance',
    '$partial_label' => 'Partial Due',
    '$client1_label' => '',
    '$client2_label' => '',
    '$client3_label' => '',
    '$client4_label' => '',
    '$dueDate_label' => 'Due Date',
    '$invoice_label' => 'Invoice Number',
    '$account_label' => 'Company Name',
    '$country_label' => 'Country',
    '$contact_label' => 'Name',
    '$app_url_label' => '',
    '$website_label' => 'Website',
    '$entity_label' => 'Invoice',
    '$thanks_label' => 'Thanks',
    '$amount_label' => 'Total',
    '$method_label' => 'Method',
    '$number_label' => 'Invoice Number',
    '$footer_label' => '',
    '$client_label' => 'Client Name',
    '$email_label' => 'Email',
    '$notes_label' => 'Public Notes',
    '_rate1_label' => 'Tax',
    '_rate2_label' => 'Tax',
    '_rate3_label' => 'Tax',
    '$taxes_label' => 'Taxes',
    '$total_label' => 'Total',
    '$phone_label' => 'Phone',
    '$terms_label' => 'Invoice Terms',
    '$from_label' => 'From',
    '$item_label' => 'Item',
    '$date_label' => 'Invoice Date',
    '$tax_label' => 'Tax',
    '$dir_label' => '',
    '$to_label' => 'To',
    '$show_paid_stamp_label' => '',
    '$status_logo_label' => '',
    '$show_shipping_address_label' => '',
    '$show_shipping_address_block_label' => '',
    '$show_shipping_address_visibility_label' => '',
  ],
];
    }
}
