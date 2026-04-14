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

namespace App\Services\EDocument\Gateway\Storecove;

use App\Models\Company;
use App\Services\Email\Email;
use App\Services\Email\EmailObject;
use Illuminate\Mail\Mailables\Address;

/**
 * Forwards Peppol XML documents to an external accounting system
 * (e.g. Yuki, WinAuditor, Exact Online) via email.
 *
 * Reads the forwarding address from company->settings->e_invoice_forward_email.
 * Callers should check isConfigured() before invoking forward() to avoid
 * unnecessary work when no forwarding address is set.
 */
class EInvoiceForwarder
{
    private string $forward_email;

    public function __construct(private Company $company)
    {
        $this->forward_email = $this->company->settings->e_invoice_forward_email ?? '';
    }

    /**
     * Determines whether a valid forwarding email address is configured.
     *
     * @return bool
     */
    public function isConfigured(): bool
    {
        return filter_var($this->forward_email, FILTER_VALIDATE_EMAIL) !== false;
    }

    /**
     * Dispatches an email with the Peppol XML attached to the configured
     * forwarding address using the application's Email service.
     *
     * @param  string $xml       Raw XML content
     * @param  string $filename  Attachment filename (e.g. INV-001.xml)
     * @param  string $direction 'sent' or 'received'
     * @return void
     */
    public function forward(string $xml, string $filename, string $direction): void
    {
        $mo = new EmailObject();
        $mo->subject = "Peppol Document ({$direction}): {$filename}";
        $mo->body = "Peppol e-invoice document ({$direction}): {$filename}";
        $mo->text_body = "Peppol e-invoice document ({$direction}): {$filename}";
        $mo->company_key = $this->company->company_key;
        $mo->html_template = 'email.template.admin';
        $mo->to = [new Address($this->forward_email)];
        $mo->attachments = [
            ['file' => base64_encode($xml), 'name' => $filename],
        ];

        Email::dispatch($mo, $this->company);
    }
}
