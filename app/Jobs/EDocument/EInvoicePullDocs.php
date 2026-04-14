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

namespace App\Jobs\EDocument;

use App\Utils\Ninja;
use App\Models\Account;
use App\Models\Company;
use App\Utils\TempFile;
use App\Services\Email\Email;
use Illuminate\Bus\Queueable;
use App\Services\Email\EmailObject;
use Illuminate\Support\Facades\App;
use App\Utils\Traits\SavesDocuments;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use App\Services\EDocument\Gateway\Storecove\Storecove;
use App\Services\EDocument\Gateway\Storecove\EInvoiceForwarder;
use App\Utils\Traits\Notifications\UserNotifies;

class EInvoicePullDocs implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;
    use SavesDocuments;
    use UserNotifies;

    public $deleteWhenMissingModels = true;

    public $tries = 1;

    private int $einvoice_received_count = 0;

    public function __construct() {}

    public function handle()
    {
        nlog("Pulling Peppol Docs " . now()->format('Y-m-d h:i:s'));

        if (Ninja::isHosted()) {
            return;
        }

        Account::query()
                ->with('companies')
                ->where('e_invoice_quota', '>', 0)
                ->whereHas('companies', function ($q) {
                    $q->whereNotNull('legal_entity_id');
                })
                ->cursor()
                ->each(function ($account) {

                    $account->companies->filter(function ($company) {

                        return $company->settings->e_invoice_type == 'PEPPOL' && ($company->tax_data->acts_as_receiver ?? false);

                    })
                    ->each(function ($company) {

                        $this->einvoice_received_count = 0;

                        $response = \Illuminate\Support\Facades\Http::baseUrl(config('ninja.hosted_ninja_url'))
                            ->withHeaders([
                                'Content-Type' => 'application/json',
                                'Accept' => 'application/json',
                                'X-EInvoice-Token' => $company->account->e_invoicing_token,
                            ])
                            ->post('/api/einvoice/peppol/documents', data: [
                                'license_key' => config('ninja.license_key'),
                                'account_key' => $company->account->key,
                                'company_key' => $company->company_key,
                                'legal_entity_id' => $company->legal_entity_id,
                            ]);

                        if ($response->successful()) {

                            $hash = $response->header('X-CONFIRMATION-HASH');

                            $this->handleSuccess($response->json(), $company, $hash);
                        } else {
                            nlog($response->body());
                        }



                        if ($this->einvoice_received_count > 0) {

                            foreach ($company->company_users as $company_user) {

                                $user = $company_user->user;

                                $notifications = $this->findCompanyUserNotificationType($company_user, ['enable_e_invoice_received_notification']);

                                if (!in_array('mail', $notifications)) {
                                    continue;
                                }

                                App::setLocale($company->getLocale());

                                $mo = new EmailObject();
                                $mo->subject = ctrans('texts.einvoice_received_subject');
                                $mo->body = ctrans('texts.einvoice_received_body', ['count' => $this->einvoice_received_count]);
                                $mo->text_body = ctrans('texts.einvoice_received_body', ['count' => $this->einvoice_received_count]);
                                $mo->company_key = $company->company_key;
                                $mo->html_template = 'email.template.admin';
                                $mo->to = [new Address($user->email, $user->present()->name())];

                                Email::dispatch($mo, $company);
                            }
                        }

                        $this->pullSentDocuments($company);

                    });

                });
    }

    /**
     * Processes received documents pulled from the hosted server.
     *
     * Creates expenses and vendors from each Storecove invoice, saves
     * HTML/XML/attachments to the expense, and forwards the XML to
     * the company's forwarding email if configured. Flushes the
     * documents from S3 after processing.
     *
     * @param  array<int, array>  $received_documents
     * @param  Company            $company
     * @param  string             $hash  Confirmation hash for flushing
     * @return void
     */
    private function handleSuccess(array $received_documents, Company $company, string $hash): void
    {

        $storecove = new Storecove();
        $forwarder = new EInvoiceForwarder($company);

        foreach ($received_documents as $document) {

            nlog($document);

            if(!isset($document['document']['invoice'])) {
                nlog("No invoice found in document!!");
                continue;
            }

            $storecove_invoice = $storecove->expense->getStorecoveInvoice(json_encode($document['document']['invoice']));
            $expense = $storecove->expense->createExpense($storecove_invoice, $company);

            $file_name = $document['guid'];

            if (strlen($document['html'] ?? '') > 5) {

                $upload_document = TempFile::UploadedFileFromRaw($document['html'], "{$file_name}.html", 'text/html');
                $this->saveDocument($upload_document, $expense, true);
                $upload_document = null;
            }

            if (strlen($document['original_base64_xml'] ?? '') > 5) {

                $upload_document = TempFile::UploadedFileFromBase64($document['original_base64_xml'], "{$file_name}.xml", 'application/xml');
                $this->saveDocument($upload_document, $expense, true);
                $upload_document = null;

                if ($forwarder->isConfigured()) {
                    $forwarder->forward(base64_decode($document['original_base64_xml']), "{$file_name}.xml", 'received');
                }
            }

            if(isset($document['document']['invoice']['attachments'])){
                foreach ($document['document']['invoice']['attachments'] as $attachment) {

                    $upload_document = TempFile::UploadedFileFromBase64($attachment['document'], $attachment['filename'], $attachment['mime_type']);
                    $this->saveDocument($upload_document, $expense, true);
                    $upload_document = null;

                }
            }

            $this->einvoice_received_count++;

        }

        $response = \Illuminate\Support\Facades\Http::baseUrl(config('ninja.hosted_ninja_url'))
            ->withHeaders([
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
                'X-EInvoice-Token' => $company->account->e_invoicing_token,
            ])
            ->post('/api/einvoice/peppol/documents/flush', data: [
                'license_key' => config('ninja.license_key'),
                'account_key' => $company->account->key,
                'company_key' => $company->company_key,
                'legal_entity_id' => $company->legal_entity_id,
                'hash'  => $hash,
            ]);

        if ($response->successful()) {
        }



    }

    /**
     * Pulls confirmed-sent XML documents from the hosted server and forwards
     * them to the company's forwarding email.
     *
     * Only runs when the company has a valid forwarding email configured.
     * Retrieves sent documents via /api/einvoice/peppol/documents/sent,
     * forwards each XML, then flushes the documents from S3.
     *
     * @param  Company $company
     * @return void
     */
    private function pullSentDocuments(Company $company): void
    {
        $forwarder = new EInvoiceForwarder($company);

        if (!$forwarder->isConfigured()) {
            return;
        }

        $response = \Illuminate\Support\Facades\Http::baseUrl(config('ninja.hosted_ninja_url'))
            ->withHeaders([
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
                'X-EInvoice-Token' => $company->account->e_invoicing_token,
            ])
            ->post('/api/einvoice/peppol/documents/sent', data: [
                'license_key' => config('ninja.license_key'),
                'account_key' => $company->account->key,
                'company_key' => $company->company_key,
                'legal_entity_id' => $company->legal_entity_id,
            ]);

        if (!$response->successful()) {
            return;
        }

        $sent_documents = $response->json();
        $hash = $response->header('X-CONFIRMATION-HASH');

        if (empty($sent_documents)) {
            return;
        }

        foreach ($sent_documents as $document) {
            $guid = $document['guid'] ?? '';
            $xml_base64 = $document['xml_base64'] ?? '';

            if (strlen($xml_base64) > 5) {
                $forwarder->forward(base64_decode($xml_base64), "{$guid}.xml", 'sent');
            }
        }

        \Illuminate\Support\Facades\Http::baseUrl(config('ninja.hosted_ninja_url'))
            ->withHeaders([
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
                'X-EInvoice-Token' => $company->account->e_invoicing_token,
            ])
            ->post('/api/einvoice/peppol/documents/sent/flush', data: [
                'license_key' => config('ninja.license_key'),
                'account_key' => $company->account->key,
                'company_key' => $company->company_key,
                'legal_entity_id' => $company->legal_entity_id,
                'hash' => $hash,
            ]);
    }

    public function failed(\Throwable $exception)
    {
        nlog($exception->getMessage());
    }
}
