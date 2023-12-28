<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2023. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Jobs\Mail;

use App\Helpers\IngresMail\Transformer\ImapMailTransformer;
use App\Helpers\Mail\Mailbox\Imap\ImapMailbox;
use App\Libraries\MultiDB;
use App\Models\Company;
use App\Repositories\ExpenseRepository;
use App\Services\IngresEmail\IngresEmailEngine;
use App\Utils\Traits\MakesHash;
use App\Utils\Traits\SavesDocuments;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/*Multi Mailer implemented*/

class ExpenseMailboxJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, MakesHash, SavesDocuments;

    public $tries = 4; //number of retries

    public $deleteWhenMissingModels = true;
    private array $imap_companies;
    private array $imap_credentials;
    private $expense_repo;

    public function __construct()
    {
        $this->credentials = [];

        $this->getImapCredentials();

        $this->expense_repo = new ExpenseRepository(); // @turbo124 @todo is this the right aproach? should it be handled just with the model?
    }

    public function handle()
    {

        //multiDB environment, need to
        if (sizeOf($this->imap_credentials) == 0)
            return;

        foreach ($this->imap_companies as $companyId) {
            $company = MultiDB::findAndSetDbByCompanyId($companyId);
            if (!$company) {
                nlog("processing of an imap_mailbox skipped because of unknown companyId: " . $companyId);
                return;
            }

            try {
                nlog("start importing expenses from imap-server of company: " . $companyId);
                $this->handleImapCompany($company);

            } catch (\Exception $e) {
                nlog("processing of an imap_mailbox failed upnormally: " . $companyId . " message: " . $e->getMessage()); // @turbo124 @todo should this be handled in an other way?
            }
        }

    }

    private function getImapCredentials()
    {
        $servers = array_map('trim', explode(",", config('ninja.ingest_mail.imap.servers')));
        $ports = array_map('trim', explode(",", config('ninja.ingest_mail.imap.ports')));
        $users = array_map('trim', explode(",", config('ninja.ingest_mail.imap.users')));
        $passwords = array_map('trim', explode(",", config('ninja.ingest_mail.imap.passwords')));
        $companies = array_map('trim', explode(",", config('ninja.ingest_mail.imap.companies')));

        if (sizeOf($servers) != sizeOf($ports) || sizeOf($servers) != sizeOf($users) || sizeOf($servers) != sizeOf($passwords) || sizeOf($servers) != sizeOf($companies))
            throw new \Exception('invalid configuration ingest_mail.imap (wrong element-count)');

        foreach ($companies as $index => $companyId) {

            if ($servers[$index] == '') // if property is empty, ignore => this happens exspecialy when no config is provided and it enabled us to set a single default company for env (usefull on self-hosted)
                continue;

            $this->imap_credentials[$companyId] = [
                "server" => $servers[$index],
                "port" => $ports[$index] != '' ? $ports[$index] : null,
                "user" => $users[$index],
                "password" => $passwords[$index],
            ];
            $this->imap_companies[] = $companyId;

        }
    }

    private function handleImapCompany(Company $company)
    {
        nlog("importing expenses for company: " . $company->id);

        $credentials = $this->imap_credentials[$company->id];
        $imapMailbox = new ImapMailbox($credentials->server, $credentials->port, $credentials->user, $credentials->password);
        $transformer = new ImapMailTransformer();

        $emails = $imapMailbox->getUnprocessedEmails();


        foreach ($emails as $email) {

            try {

                $email->markAsSeen();

                IngresEmailEngine::dispatch($transformer->transform($email));

                $imapMailbox->moveProcessed($email);

            } catch (\Exception $e) {
                $imapMailbox->moveFailed($email);

                nlog("processing of an email failed upnormally: " . $company->id . " message: " . $e->getMessage());
            }

        }
    }

    public function backoff()
    {
        // return [5, 10, 30, 240];
        return [rand(5, 10), rand(30, 40), rand(60, 79), rand(160, 400)];

    }

}
