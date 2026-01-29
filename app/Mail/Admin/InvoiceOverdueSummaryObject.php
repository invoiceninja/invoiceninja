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

namespace App\Mail\Admin;

use stdClass;
use Carbon\Carbon;
use App\Utils\Ninja;
use App\Utils\Number;
use App\Models\Company;
use App\Models\Invoice;
use App\Libraries\MultiDB;
use Illuminate\Support\Facades\App;
use App\Utils\Traits\MakesDates;

class InvoiceOverdueSummaryObject
{
    use MakesDates;

    public function __construct(public array $overdue_invoices, public array $table_headers, public Company $company, public bool $use_react_url)
    {
    }

    public function build()
    {
        MultiDB::setDb($this->company->db);

        App::forgetInstance('translator');
        /* Init a new copy of the translator */
        $t = app('translator');
        /* Set the locale */
        App::setLocale($this->company->getLocale());
        /* Set customized translations _NOW_ */
        $t->replace(Ninja::transformTranslations($this->company->settings));

        $mail_obj = new stdClass();
        $mail_obj->amount = 0;
        $mail_obj->subject = $this->getSubject();
        $mail_obj->data = $this->getData();
        $mail_obj->markdown = 'email.admin.generic_table';
        $mail_obj->tag = $this->company->company_key;
        $mail_obj->text_view = 'email.admin.generic_table_text';

        return $mail_obj;
    }

    private function getSubject()
    {

        $timezone = $this->company->timezone();
        $timezone_name = $timezone ? $timezone->name : 'UTC';

        // Get the current hour in the company's timezone
        $now_in_company_tz = Carbon::now($timezone_name);
        $date = $this->translateDate($now_in_company_tz->format('Y-m-d'), $this->company->date_format(), $this->company->locale());

        return
            ctrans(
                'texts.notification_invoice_overdue_summary_subject',
                [
                    'date' => $date
                ]
            );
    }

    private function getData()
    {

        $invoice = Invoice::withTrashed()->find(reset($this->overdue_invoices)['id']);

        $overdue_invoices_collection = array_map(
            fn($row) => \Illuminate\Support\Arr::except($row, ['id', 'amount', 'due_date']),
            $this->overdue_invoices
        );
        
        $data = [
            'title' => $this->getSubject(),
            'content' => ctrans('texts.notification_invoice_overdue_summary'),
            'url' => $invoice->invitations->first()->getAdminLink($this->use_react_url),
            'button' => $this->use_react_url ? ctrans('texts.view_invoice') : ctrans('texts.login'),
            'signature' => $this->company->settings->email_signature,
            'logo' => $this->company->present()->logo(),
            'settings' => $this->company->settings,
            'whitelabel' => $this->company->account->isPaid() ? true : false,
            'text_body' => ctrans('texts.notification_invoice_overdue_summary'),
            'template' => $this->company->account->isPremium() ? 'email.template.admin_premium' : 'email.template.admin',
            'table' => $overdue_invoices_collection,
            'table_headers' => $this->table_headers,
        ];

        return $data;
    }
}

