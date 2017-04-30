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
    protected $signature = 'ninja:init-lookup {--truncate=} {--company_id=}';

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
        $this->info(date('Y-m-d h:i:s') . ' Running InitLookup...');

        config(['database.default' => DB_NINJA_LOOKUP]);

        if (DbServer::count()) {
            $dbServer = DbServer::first();
        } else {
            $dbServer = DbServer::create(['name' => DB_NINJA_1]);
        }

        if ($this->option('truncate')) {
            $this->truncateTables();
        }

        config(['database.default' => DB_NINJA_1]);

        $count = DB::table('companies')
                    ->where('id', '>=', $this->option('company_id') ?: 1)
                    ->count();

        for ($i=0; $i<$count; $i += 100) {
            $this->initCompanies($dbServer->id, $i);
        }
    }

    private function initCompanies($dbServerId, $offset = 0)
    {
        $this->info(date('Y-m-d h:i:s') . ' initCompanies - offset: ' . $offset);
        $data = [];

        config(['database.default' => DB_NINJA_1]);

        $companies = DB::table('companies')
                        ->offset($offset)
                        ->limit(100)
                        ->orderBy('id')
                        ->where('id', '>=', $this->option('company_id') ?: 1)
                        ->get(['id']);
        foreach ($companies as $company) {
            $data[$company->id] = $this->parseCompany($company->id);
        }

        config(['database.default' => DB_NINJA_LOOKUP]);

        foreach ($data as $companyId => $company) {
            $this->info(date('Y-m-d h:i:s') . ' company: ' . $companyId);

            $lookupCompany = LookupCompany::create([
                'db_server_id' => $dbServerId,
                'company_id' => $companyId,
            ]);

            foreach ($company as $accountKey => $account) {
                $lookupAccount = LookupAccount::create([
                    'lookup_company_id' => $lookupCompany->id,
                    'account_key' => $accountKey
                ]);
                foreach ($account['users'] as $user) {
                    LookupUser::create([
                        'lookup_account_id' => $lookupAccount->id,
                        'email' => $user['email'],
                        'user_id' => $user['user_id'],
                    ]);
                }
                foreach ($account['contacts'] as $contact) {
                    LookupContact::create([
                        'lookup_account_id' => $lookupAccount->id,
                        'contact_key' => $contact['contact_key'],
                    ]);
                }
                foreach ($account['invitations'] as $invitation) {
                    LookupInvitation::create([
                        'lookup_account_id' => $lookupAccount->id,
                        'invitation_key' => $invitation['invitation_key'],
                        'message_id' => $invitation['message_id'] ?: null,
                    ]);
                }
                foreach ($account['tokens'] as $token) {
                    LookupToken::create([
                        'lookup_account_id' => $lookupAccount->id,
                        'token' => $token['token'],
                    ]);
                }
            }
        }
    }

    private function parseCompany($companyId)
    {
        $data = [];

        config(['database.default' => DB_NINJA_1]);

        $accounts = DB::table('accounts')->whereCompanyId($companyId)->orderBy('id')->get(['id', 'account_key']);
        foreach ($accounts as $account) {
            $data[$account->account_key] = $this->parseAccount($account->id);
        }

        return $data;
    }

    private function parseAccount($accountId)
    {
        $data = [
            'users' => [],
            'contacts' => [],
            'invitations' => [],
            'tokens' => [],
        ];

        $users = DB::table('users')->whereAccountId($accountId)->orderBy('id')->get(['email', 'id']);
        foreach ($users as $user) {
            $data['users'][] = [
                'email' => $user->email,
                'user_id' => $user->id,
            ];
        }

        $contacts = DB::table('contacts')->whereAccountId($accountId)->orderBy('id')->get(['contact_key']);
        foreach ($contacts as $contact) {
            $data['contacts'][] = [
                'contact_key' => $contact->contact_key,
            ];
        }

        $invitations = DB::table('invitations')->whereAccountId($accountId)->orderBy('id')->get(['invitation_key', 'message_id']);
        foreach ($invitations as $invitation) {
            $data['invitations'][] = [
                'invitation_key' => $invitation->invitation_key,
                'message_id' => $invitation->message_id,
            ];
        }

        $tokens = DB::table('account_tokens')->whereAccountId($accountId)->orderBy('id')->get(['token']);
        foreach ($tokens as $token) {
            $data['tokens'][] = [
                'token' => $token->token,
            ];
        }

        return $data;
    }

    private function truncateTables()
    {
        DB::statement('SET FOREIGN_KEY_CHECKS = 0');
        DB::statement('truncate lookup_companies');
        DB::statement('truncate lookup_accounts');
        DB::statement('truncate lookup_users');
        DB::statement('truncate lookup_contacts');
        DB::statement('truncate lookup_invitations');
        DB::statement('truncate lookup_tokens');
        DB::statement('SET FOREIGN_KEY_CHECKS = 1');
    }

    protected function getOptions()
    {
        return [
            ['truncate', null, InputOption::VALUE_OPTIONAL, 'Truncate', null],
            ['company_id', null, InputOption::VALUE_OPTIONAL, 'Company Id', null],
        ];
    }

}
