<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use DB;
use App\Models\DbServer;
use App\Models\LookupCompany;
use App\Models\LookupAccount;
use App\Models\LookupUser;
use App\Models\LookupContact;
use App\Models\LookupToken;
use App\Models\LookupInvitation;

class InitLookup extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'ninja:init-lookup';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Initialize lookup tables';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $this->info(date('Y-m-d') . ' Running InitLookup...');

        config(['database.default' => DB_NINJA_0]);

        if (DbServer::count()) {
            //exit('db_server record exists!');
        }

        $dbServer = DbServer::create(['name' => DB_NINJA_1]);
        $count = DB::table('companies')->count();

        for ($i=0; $i<$count; $i += 100) {
            $this->initCompanies($offset);
        }
    }

    private function initCompanies($offset = 0)
    {
        $this->info(date('Y-m-d') . ' initCompanies - offset: ' . $offset);

        config(['database.default' => DB_NINJA_1]);

        $companies = DB::table('companies')->orderBy('id')->get(['id']);

        foreach ($companies as $company) {
            $this->parseCompany($dbServer->id, $company->id);
        }
    }

    private function parseCompany($dbServerId, $companyId)
    {
        $data = [];

        config(['database.default' => DB_NINJA_1]);

        $accounts = DB::table('accounts')->whereCompanyId($companyId)->orderBy('id')->get(['id']);
        foreach ($accounts as $account) {
            $data[$account->id] = $this->parseAccount($account->id);
        }

        print_r($data);exit;
        config(['database.default' => DB_NINJA_0]);
        ///$lookupCompany = LookupCompany::create(['db_server_id' => $dbServerId]);

    }

    private function parseAccount($accountId)
    {
        $data = [
            'users' => [],
            'contacts' => [],
            'invitations' => [],
            'tokens' => [],
        ];

        $users = DB::table('users')->whereAccountId($accountId)->orderBy('id')->get(['email']);
        foreach ($users as $user) {
            $data['users'][] = ['email' => $user->email];
        }

        $contacts = DB::table('contacts')->whereAccountId($accountId)->orderBy('id')->get(['contact_key']);
        foreach ($contacts as $contact) {
            $data['contacts'][] = ['contact_key' => $contact->contact_key];
        }

        $invitations = DB::table('invitations')->whereAccountId($accountId)->orderBy('id')->get(['invitation_key', 'message_id']);
        foreach ($invitations as $invitation) {
            $data['invitations'][] = ['invitation_key' => $invitation->invitation_key, 'message_id' => $invitation->message_id];
        }

        $tokens = DB::table('account_tokens')->whereAccountId($accountId)->orderBy('id')->get(['token']);
        foreach ($tokens as $token) {
            $data['tokens'][] = ['token' => $token->token];
        }

        return $data;
    }
}
