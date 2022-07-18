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

namespace App\Utils\Traits;

/**
 * Class MakesTemplateData.
 */
trait MakesTemplateData
{
    public function makeFakerLabels() :array
    {
        $data = [];

        $values = $this->getFakerData();

        foreach ($values as $key => $value) {
            $data[$key.'_label'] = $value['label'];
        }

        return $data;
    }

    /**
     * Transforms all placeholders
     * to invoice values.
     *
     * @return array returns an array
     * of keyed labels (appended with _label)
     */
    public function makeFakerValues() :array
    {
        $data = [];

        $values = $this->getFakerData();

        foreach ($values as $key => $value) {
            $data[$key] = $value['value'];
        }

        return $data;
    }

    public function getFakerData()
    {
        $data = [];

        $data['$tax'] = ['value' => '', 'label' => ctrans('texts.tax')];
        $data['$app_url'] = ['value' => 'https://example.com', 'label' => ''];
        $data['$from'] = ['value' => '', 'label' => ctrans('texts.from')];
        $data['$to'] = ['value' => '', 'label' => ctrans('texts.to')];
        $data['$total_tax_labels'] = ['value' => '<span>VAT</span>', 'label' => ctrans('texts.taxes')];
        $data['$total_tax_values'] = ['value' => '<span>17.5%</span>', 'label' => ctrans('texts.taxes')];
        $data['$line_tax_labels'] = ['value' => '<span>VAT</span>', 'label' => ctrans('texts.taxes')];
        $data['$line_tax_values'] = ['value' => '<span>17.5%</span>', 'label' => ctrans('texts.taxes')];
        $data['$date'] = ['value' => '2010-02-02', 'label' => ctrans('texts.date')];
        $data['$invoice_date'] = ['value' => '2010-02-02', 'label' => ctrans('texts.invoice_date')];
        $data['$invoice.date'] = &$data['$date'];
        $data['$due_date'] = ['value' => '2010-02-02', 'label' => ctrans('texts.due_date')];
        $data['$invoice.due_date'] = &$data['$due_date'];
        $data['$invoice.number'] = ['value' => '#INV-20293', 'label' => ctrans('texts.invoice_number')];
        $data['$invoice.invoice_number'] = &$data['$invoice.number'];
        $data['$invoice_number'] = &$data['$invoice.number'];
        $data['$po_number'] = ['value' => '#PO-12322', 'label' => ctrans('texts.po_number')];
        $data['$invoice.po_number'] = &$data['$po_number'];
        $data['$line_taxes'] = &$data['$line_tax_labels'];
        $data['$invoice.line_taxes'] = &$data['$line_tax_labels'];
        $data['$total_taxes'] = &$data['$line_tax_labels'];
        $data['$invoice.total_taxes'] = &$data['$total_taxes'];
        $data['$entity_label'] = ['value' => '', 'label' => ctrans('texts.invoice')];
        $data['$number'] = ['value' => '#ENT-292', 'label' => ctrans('texts.invoice_number')];
        $data['$entity.terms'] = ['value' => 'The terms and conditions are listed below and are non negotiable', 'label' => ctrans('texts.invoice_terms')];
        $data['$terms'] = &$data['$entity.terms'];
        $data['$entity_number'] = &$data['$number'];
        $data['$discount'] = ['value' => '$10.00', 'label' => ctrans('texts.discount')];
        $data['$invoice.discount'] = &$data['$discount'];
        $data['$subtotal'] = ['value' => '$20.00', 'label' => ctrans('texts.subtotal')];
        $data['$invoice.subtotal'] = &$data['$subtotal'];
        $data['$balance_due'] = ['value' => '$5.00', 'label' => ctrans('texts.balance_due')];
        $data['$invoice.balance_due'] = &$data['$balance_due'];
        $data['$partial_due'] = ['value' => '$5.00', 'label' => ctrans('texts.partial_due')];
        $data['$invoice.partial_due'] = &$data['$partial_due'];
        $data['$total'] = ['value' => '$100.00', 'label' => ctrans('texts.total')];
        $data['$invoice.total'] = ['value' => '$100.00', 'label' => ctrans('texts.invoice_total')];
        $data['$amount'] = &$data['$total'];
        $data['$invoice_total'] = &$data['$total'];
        $data['$invoice.amount'] = &$data['$total'];
        $data['$quote_total'] = ['value' => '$100.00', 'label' => ctrans('texts.quote_total')];
        $data['$quote.amount'] = &$data['$quote_total'];
        $data['$credit_total'] = ['value' => '$100.00', 'label' => ctrans('texts.credit_total')];
        $data['$credit.total'] = &$data['$credit_total'];
        $data['$balance'] = ['value' => '$100.00', 'label' => ctrans('texts.balance')];
        $data['$invoice.balance'] = &$data['$balance'];
        $data['$taxes'] = ['value' => '$10.00', 'label' => ctrans('texts.taxes')];
        $data['$invoice.taxes'] = &$data['$taxes'];
        $data['$invoice1'] = ['value' => '10', 'label' => 'invoice1'];
        $data['$invoice2'] = ['value' => '10', 'label' => 'invoice2'];
        $data['$invoice3'] = ['value' => '10', 'label' => 'invoice3'];
        $data['$invoice4'] = ['value' => '10', 'label' => 'invoice4'];
        $data['$invoice.public_notes'] = ['value' => '10', 'label' => ctrans('texts.public_notes')];
        $data['$entity.public_notes'] = &$data['$invoice.public_notes'];
        $data['$quote_date'] = ['value' => '2010-02-03', 'label' => ctrans('texts.quote_date')];
        $data['$quote_number'] = ['value' => '#QUOTE-19338', 'label' => ctrans('texts.quote_number')];
        $data['$quote.quote_number'] = &$data['$quote_number'];
        $data['$quote_no'] = &$data['$quote_number'];
        $data['$quote.quote_no'] = &$data['$quote_number'];
        $data['$valid_until'] = ['value' => '2010-02-03', 'label' => ctrans('texts.valid_until')];
        $data['$quote_total'] = ['value' => '$20.00', 'label' => ctrans('texts.quote_total')];
        $data['$credit_amount'] = ['value' => '$15.00', 'label' => ctrans('texts.credit_amount')];
        $data['$credit_balance'] = ['value' => '$12.00', 'label' => ctrans('texts.credit_balance')];

        $data['$credit_number'] = &$data['$number'];
        $data['$credit_no'] = &$data['$number'];
        $data['$credit.credit_no'] = &$data['$number'];
        $data['$invoice_no'] = &$data['$number'];
        $data['$invoice.invoice_no'] = &$data['$number'];
        $data['$client1'] = ['value' => 'Client Custom Values', 'label' => 'client 1'];
        $data['$client2'] = ['value' => 'Client Custom Values', 'label' => 'client 2'];
        $data['$client3'] = ['value' => 'Client Custom Values', 'label' => 'client 3'];
        $data['$client4'] = ['value' => 'Client Custom Values', 'label' => 'client 4'];
        $data['$address1'] = ['value' => '5 Jimbuckeroo Way', 'label' => ctrans('texts.address1')];
        $data['$address2'] = ['value' => 'Kalamazoo', 'label' => ctrans('texts.address2')];
        $data['$id_number'] = ['value' => 'ID Number', 'label' => ctrans('texts.id_number')];
        $data['$vat_number'] = ['value' => '555-434-324', 'label' => ctrans('texts.vat_number')];
        $data['$website'] = ['value' => 'https://www.invoiceninja.com', 'label' => ctrans('texts.website')];
        $data['$phone'] = ['value' => '555-12321', 'label' => ctrans('texts.phone')];
        $data['$country'] = ['value' => 'USA', 'label' => ctrans('texts.country')];
        $data['$email'] = ['value' => 'user@example.com', 'label' => ctrans('texts.email')];
        $data['$client_name'] = ['value' => 'Joe Denkins', 'label' => ctrans('texts.client_name')];
        $data['$client.balance'] = ['value' => '$100', 'label' => ctrans('texts.account_balance')];
        $data['$client.name'] = &$data['$client_name'];
        $data['$client.address1'] = &$data['$address1'];
        $data['$client.address2'] = &$data['$address2'];
        $data['$client_address'] = ['value' => '5 Kalamazoo Way\n Jimbuckeroo\n USA 90210', 'label' => ctrans('texts.address')];
        $data['$client.address'] = &$data['$client_address'];
        $data['$client.id_number'] = &$data['$id_number'];
        $data['$client.vat_number'] = &$data['$vat_number'];
        $data['$client.website'] = &$data['$website'];
        $data['$client.phone'] = &$data['$phone'];
        $data['$city_state_postal'] = ['value' => 'Los Angeles, CA, 90210', 'label' => ctrans('texts.city_state_postal')];
        $data['$client.city_state_postal'] = &$data['$city_state_postal'];
        $data['$postal_city_state'] = ['value' => '90210, Los Angeles, CA', 'label' => ctrans('texts.postal_city_state')];
        $data['$client.postal_city_state'] = &$data['$postal_city_state'];
        $data['$client.country'] = &$data['$country'];
        $data['$client.email'] = &$data['$email'];
        $data['$contact_name'] = ['value' => 'Jimmy Nadel', 'label' => ctrans('texts.contact_name')];
        $data['$contact.name'] = &$data['$contact_name'];
        $data['$contact1'] = ['value' => 'Custom Contact Values', 'label' => 'contact 1'];
        $data['$contact2'] = ['value' => 'Custom Contact Values', 'label' => 'contact 2'];
        $data['$contact3'] = ['value' => 'Custom Contact Values', 'label' => 'contact 3'];
        $data['$contact4'] = ['value' => 'Custom Contact Values', 'label' => 'contact 4'];
        $data['$company.city_state_postal'] = ['value' => 'Los Angeles, CA, 90210', 'label' => ctrans('texts.city_state_postal')];
        $data['$company.postal_city_state'] = ['value' => '90210, Los Angeles, CA', 'label' => ctrans('texts.postal_city_state')];
        $data['$company.name'] = ['value' => 'ACME co', 'label' => ctrans('texts.company_name')];
        $data['$company.company_name'] = &$data['$company.name'];
        $data['$company.address1'] = ['value' => '5 Jimbuckeroo Way', 'label' => ctrans('texts.address1')];
        $data['$company.address2'] = ['value' => 'Kalamazoo', 'label' => ctrans('texts.address2')];
        $data['$company.city'] = ['value' => 'Los Angeles', 'label' => ctrans('texts.city')];
        $data['$company.state'] = ['value' => 'CA', 'label' => ctrans('texts.state')];
        $data['$company.postal_code'] = ['value' => '90210', 'label' => ctrans('texts.postal_code')];
        $data['$company.country'] = ['value' => 'USA', 'label' => ctrans('texts.country')];
        $data['$company.phone'] = ['value' => '555-3432', 'label' => ctrans('texts.phone')];
        $data['$company.email'] = ['value' => 'user@example.com', 'label' => ctrans('texts.email')];
        $data['$company.vat_number'] = ['value' => 'VAT-3344-223', 'label' => ctrans('texts.vat_number')];
        $data['$company.id_number'] = ['value' => 'ID-NO-#434', 'label' => ctrans('texts.id_number')];
        $data['$company.website'] = ['value' => 'https://invoiceninja.com', 'label' => ctrans('texts.website')];
        $data['$company.address'] = ['value' => '5 Kalamazoo Way\n Jimbuckeroo\n USA 90210', 'label' => ctrans('texts.address')];
        $data['$company.logo'] = ['value' => "<img src='https://raw.githubusercontent.com/hillelcoren/invoice-ninja/master/public/images/round_logo.png' class='w-48' alt='logo'>", 'label' => ctrans('texts.logo')];
        $data['$company_logo'] = &$data['$company.logo'];
        $data['$company1'] = ['value' => 'Company Custom Value 1', 'label' => 'company label1'];
        $data['$company2'] = ['value' => 'Company Custom Value 2', 'label' => 'company label2'];
        $data['$company3'] = ['value' => 'Company Custom Value 3', 'label' => 'company label3'];
        $data['$company4'] = ['value' => 'Company Custom Value 4', 'label' => 'company label4'];
        $data['$product.date'] = ['value' => '2010-02-03', 'label' => ctrans('texts.date')];
        $data['$product.discount'] = ['value' => '5%', 'label' => ctrans('texts.discount')];
        $data['$product.product_key'] = ['value' => 'key', 'label' => ctrans('texts.product_key')];
        $data['$product.notes'] = ['value' => 'Product Stuff', 'label' => ctrans('texts.notes')];
        $data['$product.cost'] = ['value' => '$10.00', 'label' => ctrans('texts.cost')];
        $data['$product.quantity'] = ['value' => '1', 'label' => ctrans('texts.quantity')];
        $data['$product.tax_name1'] = ['value' => 'GST', 'label' => ctrans('texts.tax')];
        $data['$product.tax_name2'] = ['value' => 'VAT', 'label' => ctrans('texts.tax')];
        $data['$product.tax_name3'] = ['value' => 'Sales TAX', 'label' => ctrans('texts.tax')];
        $data['$product.line_total'] = ['value' => '$20.00', 'label' => ctrans('texts.line_total')];
        $data['$task.date'] = ['value' => '2010-02-03', 'label' => ctrans('texts.date')];
        $data['$task.discount'] = ['value' => '5%', 'label' => ctrans('texts.discount')];
        $data['$task.service'] = ['value' => 'key', 'label' => ctrans('texts.service')];
        $data['$task.notes'] = ['value' => 'Note for Tasks', 'label' => ctrans('texts.notes')];
        $data['$task.rate'] = ['value' => '$100.00', 'label' => ctrans('texts.rate')];
        $data['$task.hours'] = ['value' => '1', 'label' => ctrans('texts.hours')];
        $data['$task.tax_name1'] = ['value' => 'GST', 'label' => ctrans('texts.tax')];
        $data['$task.tax_name2'] = ['value' => 'VAT', 'label' => ctrans('texts.tax')];
        $data['$task.tax_name3'] = ['value' => 'CA Sales Tax', 'label' => ctrans('texts.tax')];
        $data['$task.line_total'] = ['value' => '$100.00', 'label' => ctrans('texts.line_total')];

        $data['$vendor_name'] = ['value' => 'Joey Diaz Denkins', 'label' => ctrans('texts.vendor_name')];;
        $data['$vendor.name'] = &$data['$vendor_name'];
        $data['$vendor'] = &$data['$vendor_name'];

        $data['$vendor.address1'] = &$data['$address1'];
        $data['$vendor.address2'] = &$data['$address2'];
        $data['$vendor_address'] = ['value' => '5 Kalamazoo Way\n Jimbuckeroo\n USA 90210', 'label' => ctrans('texts.address')];
        $data['$vendor.address'] = &$data['$vendor_address'];
        $data['$vendor.postal_code'] = ['value' => '90210', 'label' => ctrans('texts.postal_code')];
        $data['$vendor.public_notes'] = $data['$invoice.public_notes'];
        $data['$vendor.city'] = &$data['$company.city'];
        $data['$vendor.state'] = &$data['$company.state'];
        $data['$vendor.id_number'] = &$data['$id_number'];
        $data['$vendor.vat_number'] = &$data['$vat_number'];
        $data['$vendor.website'] = &$data['$website'];
        $data['$vendor.phone'] = &$data['$phone'];
        $data['$vendor.city_state_postal'] = &$data['$city_state_postal'];
        $data['$vendor.postal_city_state'] = &$data['$postal_city_state'];
        $data['$vendor.country'] = &$data['$country'];
        $data['$vendor.email'] = &$data['$email'];
        
        $data['$vendor.billing_address1'] = &$data['$vendor.address1'];
        $data['$vendor.billing_address2'] = &$data['$vendor.address2'];
        $data['$vendor.billing_city'] = &$data['$vendor.city'];
        $data['$vendor.billing_state'] = &$data['$vendor.state'];
        $data['$vendor.billing_postal_code'] = &$data['$vendor.postal_code'];
        $data['$vendor.billing_country'] = &$data['$vendor.country'];

        $arrKeysLength = array_map('strlen', array_keys($data));
        array_multisort($arrKeysLength, SORT_DESC, $data);

        return $data;
    }
}
