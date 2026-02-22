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

namespace App\Console\Commands;

use App\Utils\Ninja;
use App\Models\Account;
use App\Models\Company;
use App\Utils\TempFile;
use Illuminate\Console\Command;
use App\Utils\Traits\SavesDocuments;
use App\Services\EDocument\Gateway\Storecove\Storecove;

class PullPeppolDocs extends Command
{
    use SavesDocuments;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'ninja:pull-peppol-docs';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Pull Peppol docs from the E-Invoice API';

    /**
     * Execute the console command.
     *
     * @return mixed
     * @throws \Exception
     */
    public function handle()
    {
        set_time_limit(3600);

        if (Ninja::isHosted()) {
            $this->info("Pulling Peppol docs is not supported on hosted.");
            return;
        }

        $this->info("Pulling Peppol docs from the E-Invoice API");

        $account = Account::first();

        $quota_count = $account->e_invoice_quota;

        $this->info("E-Invoice Quota Remaining: $quota_count");

        $this->info("E-Invoice Token: " . $account->e_invoicing_token);

        if (!isset($account->e_invoicing_token)) {

            $this->info("No e-invoicing token found! You will not be able to authenticate with the E-Invoice API. Try logging out and back in again.");
            
            $this->info("Updating Token...");

            $response_array = $this->updateToken($account);

            $this->info($response[1]);

            if($response_array[0] != 200){

                $this->error("Failed to update token exiting");
                return;
            } 

        }

        $this->info("License key in use: " . config('ninja.license_key'));

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

                $this->info("Pulling Peppol docs for company: {$company->present()->name()}");

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

                    $this->info($response->body() );
                    
                    $this->handleSuccess($response->json(), $company, $hash);
                } else {
                    nlog($response->body());
                }

            });

        });

    }

    private function updateToken(Account $account): array
    {
        $response = \Illuminate\Support\Facades\Http::baseUrl(config('ninja.hosted_ninja_url'))
            ->withHeaders([
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ])
            ->post('/api/einvoice/tokens/rotate', data: [
                'license' => config('ninja.license_key'),
                'account_key' => $account->key,
            ]);


        if ($response->successful()) {
            
            $account->update([
                'e_invoicing_token' => $response->json('token'),
            ]);

            return [200, $response->json('token')];
            // return 'success';
        }

        return [422, $response->body()];
    }

    /**
     * Handle the success of the Peppol docs pull
     *
     * @param array $received_documents
     * @param Company $company
     * @param string $hash
     * @return void
     */
    private function handleSuccess(array $received_documents, Company $company, string $hash): void
    {

        $storecove = new Storecove();

        $doc_count = count($received_documents);

        $this->info("{$doc_count} documents found.");

        if ($doc_count > 0) {
            $this->info('Processing documents...');

            foreach ($received_documents as $document) {

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
                }

                foreach ($document['document']['invoice']['attachments'] as $attachment) {

                    $upload_document = TempFile::UploadedFileFromBase64($attachment['document'], $attachment['filename'], $attachment['mime_type']);
                    $this->saveDocument($upload_document, $expense, true);
                    $upload_document = null;

                }

                $this->info("Document {$file_name} processed.");

            }

            $this->info("Finished processing documents, flushing upstream...");

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

            $this->info("Finished flushing upstream.");
            $this->info("Finished task!");
        }
    }

}
