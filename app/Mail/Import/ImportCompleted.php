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

namespace App\Mail\Import;

use App\Models\Company;
use App\Utils\Ninja;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\App;

class ImportCompleted extends Mailable
{
    // use Queueable, SerializesModels;

    /** @var Company */
    public $company;

    /**
     * Create a new message instance.
     *
     * @return void
     */
    public $data;

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
            'client_count' => $this->company->clients()->count(),
            'product_count' => $this->company->products()->count(),
            'invoice_count' => $this->company->invoices()->count(),
            'quote_count' => $this->company->quotes()->count(),
            'credit_count' => $this->company->credits()->count(),
            'project_count' => $this->company->projects()->count(),
            'task_count' => $this->company->tasks()->count(),
            'vendor_count' => $this->company->vendors()->count(),
            'payment_count' => $this->company->payments()->count(),
            'recurring_invoice_count' => $this->company->recurring_invoices()->count(),
            'expense_count' => $this->company->expenses()->count(),
            'company_gateway_count' => $this->company->company_gateways()->count(),
            'client_gateway_token_count' => $this->company->client_gateway_tokens()->count(),
            'tax_rate_count' => $this->company->tax_rates()->count(),
            'document_count' => $this->company->documents()->count(),

        ]);

        return $this
            ->from(config('mail.from.address'), config('mail.from.name'))
            ->view('email.import.completed', $data);
    }
}
