<?php
/**
 * Invoice Ninja (https://invoiceninja.com)
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2019. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://opensource.org/licenses/AAL
 */

namespace App\Utils\Traits;

use App\Utils\Number;
use Illuminate\Support\Facades\Log;

/**
 * Class MakesInvoiceValues
 * @package App\Utils\Traits
 */
trait MakesInvoiceValues
{

    public function makeValues()
    {
        $data = [];

            //$data['invoice'] = ;
            $data['invoice_date'] = $this->invoice_date;
            $data['due_date'] = $this->due_date;
            $data['invoice_number'] = $this->invoice_number;
            $data['po_number'] = $this->po_number;
            // $data['discount'] = ;
            // $data['taxes'] = ;
            // $data['tax'] = ;
            // $data['item'] = ;
            // $data['description'] = ;
            // $data['unit_cost'] = ;
            // $data['quantity'] = ;
            // $data['line_total'] = ;
            // $data['subtotal'] = ;
    //        $data['paid_to_date'] = ;
            $data['balance_due'] = Number::formatMoney($this->balance, $this->client->currency(), $this->client->country, $this->client->settings);
            $data['partial_due'] = Number::formatMoney($this->partial, $this->client->currency(), $this->client->country, $this->client->settings);
            $data['terms'] = $this->terms;
            // $data['your_invoice'] = ;
            // $data['quote'] = ;
            // $data['your_quote'] = ;
            // $data['quote_date'] = ;
            // $data['quote_number'] = ;
            $data['total'] = Number::formatMoney($this->amount, $this->client->currency(), $this->client->country, $this->client->settings);
            // $data['invoice_issued_to'] = ;
            // $data['quote_issued_to'] = ;
            // $data['rate'] = ;
            // $data['hours'] = ;
            // $data['balance'] = ;
            // $data['from'] = ;
            // $data['to'] = ;
            // $data['invoice_to'] = ;
            // $data['quote_to'] = ;
            // $data['details'] = ;
            $data['invoice_no'] = $this->invoice_number;
            // $data['quote_no'] = ;
            // $data['valid_until'] = ;
            $data['client_name'] = $this->present()->clientName();
            $data['address1'] = $this->client->address1;
            $data['address2'] = $this->client->address2;
            $data['id_number'] = $this->client->id_number;
            $data['vat_number'] = $this->client->vat_number;
            $data['city_state_postal'] = $this->present()->cityStateZip($this->client->city, $this->client->state, $this->client->postal_code, FALSE);
            $data['postal_city_state'] = $this->present()->cityStateZip($this->client->city, $this->client->state, $this->client->postal_code, TRUE);
            $data['country'] = $this->client->country->name;
            $data['email'] = isset($this->client->primary_contact()->first()->email) ?: 'no primary contact set';
            $data['contact_name'] = $this->client->present()->primary_contact_name();
            $data['company_name'] = $this->company->name;
            $data['website'] = $this->client->website;
            $data['phone'] = $this->client->primary_contact->first()->phone;
            //$data['blank'] = ;
            //$data['surcharge'] = ;
            /*
            $data['tax_invoice'] = 
            $data['tax_quote'] = 
            $data['statement'] = ;
            $data['statement_date'] = ;
            $data['your_statement'] = ;
            $data['statement_issued_to'] = ;
            $data['statement_to'] = ;
            $data['credit_note'] = ;
            $data['credit_date'] = ;
            $data['credit_number'] = ;
            $data['credit_issued_to'] = ;
            $data['credit_to'] = ;
            $data['your_credit'] = ;
            $data['work_phone'] = ;
            $data['invoice_total'] = ;
            $data['outstanding'] = ;
            $data['invoice_due_date'] = ;
            $data['quote_due_date'] = ;
            $data['service'] = ;
            $data['product_key'] = ;
            $data['unit_cost'] = ;
            $data['custom_value1'] = ;
            $data['custom_value2'] = ;
            $data['delivery_note'] = ;
            $data['date'] = ;
            $data['method'] = ;
            $data['payment_date'] = ;
            $data['reference'] = ;
            $data['amount'] = ;
            $data['amount_paid'] =;
        	*/

        return $data;
    }

}