<?php

/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2025. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Mail\Import;

use App\Models\Company;
use App\Utils\Ninja;
use Illuminate\Mail\Mailable;
use Illuminate\Support\Facades\App;

class CsvImportCompleted extends Mailable
{
    // use Queueable, SerializesModels;

    /** @var Company */
    public $company;

    /**
     * @var array $data Array containing the necessary params.
     *
     *   $data = [
     *       'errors'  => (array) $errors,
     *       'company' => Company $company,
     *       'entity_count' => (array) $entity_count
     *   ];
     */
    public $data;

    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct(Company $company, $data)
    {
        $this->company = $company;

        $this->data = $data;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        App::forgetInstance('translator');
        App::setLocale($this->company->getLocale());

        $t = app('translator');
        $t->replace(Ninja::transformTranslations($this->company->settings));

        $data = array_merge($this->data, [
            'logo' => $this->company->present()->logo(),
            'settings' => $this->company->settings,
            'company' => $this->company,
            'client_count' => isset($this->data['entity_count']['clients']) ? $this->data['entity_count']['clients'] : false,
            'product_count' => isset($this->data['entity_count']['products']) ? $this->data['entity_count']['products'] : false,
            'invoice_count' => isset($this->data['entity_count']['invoices']) ? $this->data['entity_count']['invoices'] : false,
            'quote_count' => isset($this->data['entity_count']['quotes']) ? $this->data['entity_count']['quotes'] : false,
            'credit_count' => isset($this->data['entity_count']['credits']) ? $this->data['entity_count']['credits'] : false,
            'project_count' => isset($this->data['entity_count']['projects']) ? $this->data['entity_count']['projects'] : false,
            'task_count' => isset($this->data['entity_count']['tasks']) ? $this->data['entity_count']['tasks'] : false,
            'vendor_count' => isset($this->data['entity_count']['vendors']) ? $this->data['entity_count']['vendors'] : false,
            'payment_count' => isset($this->data['entity_count']['payments']) ? $this->data['entity_count']['payments'] : false,
            'recurring_invoice_count' => isset($this->data['entity_count']['recurring_invoices']) ? $this->data['entity_count']['recurring_invoices'] : false,
            'expense_count' => isset($this->data['entity_count']['expenses']) ? $this->data['entity_count']['expenses'] : false,
            'company_gateway_count' => isset($this->data['entity_count']['company_gateways']) ? $this->data['entity_count']['company_gateways'] : false,
            'client_gateway_token_count' => isset($this->data['entity_count']['client_gateway_tokens']) ? $this->data['entity_count']['client_gateway_tokens'] : false,
            'tax_rate_count' => isset($this->data['entity_count']['tax_rates']) ? $this->data['entity_count']['tax_rates'] : false,
            'document_count' => isset($this->data['entity_count']['documents']) ? $this->data['entity_count']['documents'] : false,
            'transaction_count' => isset($this->data['entity_count']['transactions']) ? $this->data['entity_count']['transactions'] : false,
        ]);

        return $this
            ->subject(ctrans('texts.import_completed'))
            ->from(config('mail.from.address'), config('mail.from.name'))
            ->text('email.import.csv_completed_text')
            ->view('email.import.csv_completed', $data);
    }
}
