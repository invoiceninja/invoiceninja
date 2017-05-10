<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use DB;
use Mail;
use Exception;
use App\Models\DbServer;
use App\Models\LookupCompany;
use App\Models\LookupAccount;
use App\Models\LookupUser;
use App\Models\LookupContact;
use App\Models\LookupAccountToken;
use App\Models\LookupInvitation;

class InitLookup extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'ninja:init-lookup {--truncate=} {--validate=} {--company_id=} {--page_size=100} {--database=db-ninja-1}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Initialize lookup tables';

    protected $log = '';
    protected $isValid = true;

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
        $this->logMessage('Running InitLookup...');

        config(['database.default' => DB_NINJA_LOOKUP]);

        $database = $this->option('database');
        $dbServer = DbServer::whereName($database)->first();

        if ($this->option('truncate')) {
            $this->truncateTables();
            $this->logMessage('Truncated');
        } else {
            config(['database.default' => $this->option('database')]);

            $count = DB::table('companies')
                        ->where('id', '>=', $this->option('company_id') ?: 1)
                        ->count();

            for ($i=0; $i<$count; $i += (int) $this->option('page_size')) {
                $this->initCompanies($dbServer->id, $i);
            }
        }

        $this->info($this->log);
        $this->info('Valid: ' . ($this->isValid ? RESULT_SUCCESS : RESULT_FAILURE));

        if ($this->option('validate')) {
            if ($errorEmail = env('ERROR_EMAIL')) {
                Mail::raw($this->log, function ($message) use ($errorEmail, $database) {
                    $message->to($errorEmail)
                            ->from(CONTACT_EMAIL)
                            ->subject("Check-Lookups [{$database}]: " . strtoupper($this->isValid ? RESULT_SUCCESS : RESULT_FAILURE));
                });
            } elseif (! $this->isValid) {
                throw new Exception('Check lookups failed!!');
            }
        }
    }

    private function initCompanies($dbServerId, $offset = 0)
    {
        $data = [];

        config(['database.default' => $this->option('database')]);

        $companies = DB::table('companies')
                        ->offset($offset)
                        ->limit((int) $this->option('page_size'))
                        ->orderBy('id')
                        ->where('id', '>=', $this->option('company_id') ?: 1)
                        ->get(['id']);
        foreach ($companies as $company) {
            $data[$company->id] = $this->parseCompany($company->id);
        }

        config(['database.default' => DB_NINJA_LOOKUP]);

        foreach ($data as $companyId => $company) {

            if ($this->option('validate')) {
                $lookupCompany = LookupCompany::whereDbServerId($dbServerId)->whereCompanyId($companyId)->first();
                if (! $lookupCompany) {
                    $this->logError("LookupCompany - dbServerId: {$dbServerId}, companyId: {$companyId} | Not found!");
                    continue;
                }
            } else {
                $lookupCompany = LookupCompany::create([
                    'db_server_id' => $dbServerId,
                    'company_id' => $companyId,
                ]);
            }

            foreach ($company as $accountKey => $account) {
                if ($this->option('validate')) {
                    $lookupAccount = LookupAccount::whereLookupCompanyId($lookupCompany->id)->whereAccountKey($accountKey)->first();
                    if (! $lookupAccount) {
                        $this->logError("LookupAccount - lookupCompanyId: {$lookupCompany->id}, accountKey {$accountKey} | Not found!");
                        continue;
                    }
                } else {
                    $lookupAccount = LookupAccount::create([
                        'lookup_company_id' => $lookupCompany->id,
                        'account_key' => $accountKey
                    ]);
                }

                foreach ($account['users'] as $user) {
                    if ($this->option('validate')) {
                        $lookupUser = LookupUser::whereLookupAccountId($lookupAccount->id)->whereUserId($user['user_id'])->first();
                        if (! $lookupUser) {
                            $this->logError("LookupUser - lookupAccountId: {$lookupAccount->id}, userId: {$user['user_id']} | Not found!");
                            continue;
                        }
                    } else {
                        LookupUser::create([
                            'lookup_account_id' => $lookupAccount->id,
                            'email' => $user['email'] ?: null,
                            'user_id' => $user['user_id'],
                            'oauth_user_key' => $user['oauth_user_key'],
                        ]);
                    }
                }

                foreach ($account['contacts'] as $contact) {
                    if ($this->option('validate')) {
                        $lookupContact = LookupContact::whereLookupAccountId($lookupAccount->id)->whereContactKey($contact['contact_key'])->first();
                        if (! $lookupContact) {
                            $this->logError("LookupContact - lookupAccountId: {$lookupAccount->id}, contactKey: {$contact['contact_key']} | Not found!");
                            continue;
                        }
                    } else {
                        LookupContact::create([
                            'lookup_account_id' => $lookupAccount->id,
                            'contact_key' => $contact['contact_key'],
                        ]);
                    }
                }

                foreach ($account['invitations'] as $invitation) {
                    if ($this->option('validate')) {
                        $lookupInvitation = LookupInvitation::whereLookupAccountId($lookupAccount->id)->whereInvitationKey($invitation['invitation_key'])->first();
                        if (! $lookupInvitation) {
                            $this->logError("LookupInvitation - lookupAccountId: {$lookupAccount->id}, invitationKey: {$invitation['invitation_key']} | Not found!");
                            continue;
                        }
                    } else {
                        LookupInvitation::create([
                            'lookup_account_id' => $lookupAccount->id,
                            'invitation_key' => $invitation['invitation_key'],
                            'message_id' => $invitation['message_id'] ?: null,
                        ]);
                    }
                }

                foreach ($account['tokens'] as $token) {
                    if ($this->option('validate')) {
                        $lookupToken = LookupAccountToken::whereLookupAccountId($lookupAccount->id)->whereToken($token['token'])->first();
                        if (! $lookupToken) {
                            $this->logError("LookupAccountToken - lookupAccountId: {$lookupAccount->id}, token: {$token['token']} | Not found!");
                            continue;
                        }
                    } else {
                        LookupAccountToken::create([
                            'lookup_account_id' => $lookupAccount->id,
                            'token' => $token['token'],
                        ]);
                    }
                }
            }
        }
    }

    private function parseCompany($companyId)
    {
        $data = [];

        config(['database.default' => $this->option('database')]);

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

        $users = DB::table('users')->whereAccountId($accountId)->orderBy('id')->get(['email', 'id', 'oauth_user_id', 'oauth_provider_id']);
        foreach ($users as $user) {
            $data['users'][] = [
                'email' => $user->email,
                'user_id' => $user->id,
                'oauth_user_key' => ($user->oauth_provider_id && $user->oauth_user_id) ? ($user->oauth_provider_id . '-' . $user->oauth_user_id) : null,
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

    private function logMessage($str)
    {
        $this->log .= date('Y-m-d h:i:s') . ' ' . $str . "\n";
    }

    private function logError($str)
    {
        $this->isValid = false;
        $this->logMessage($str);
    }

    private function truncateTables()
    {
        DB::statement('SET FOREIGN_KEY_CHECKS = 0');
        DB::statement('truncate lookup_companies');
        DB::statement('truncate lookup_accounts');
        DB::statement('truncate lookup_users');
        DB::statement('truncate lookup_contacts');
        DB::statement('truncate lookup_invitations');
        DB::statement('truncate lookup_account_tokens');
        DB::statement('SET FOREIGN_KEY_CHECKS = 1');
    }

    protected function getOptions()
    {
        return [
            ['truncate', null, InputOption::VALUE_OPTIONAL, 'Truncate', null],
            ['company_id', null, InputOption::VALUE_OPTIONAL, 'Company Id', null],
            ['page_size', null, InputOption::VALUE_OPTIONAL, 'Page Size', null],
            ['database', null, InputOption::VALUE_OPTIONAL, 'Database', null],
            ['validate', null, InputOption::VALUE_OPTIONAL, 'Validate', null],
        ];
    }

}
