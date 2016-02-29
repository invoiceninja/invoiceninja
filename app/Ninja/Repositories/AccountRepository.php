<?php namespace App\Ninja\Repositories;

use Auth;
use Request;
use Session;
use Utils;
use DB;
use URL;
use stdClass;
use Validator;
use Schema;
use App\Models\AccountGateway;
use App\Models\Invitation;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Client;
use App\Models\Language;
use App\Models\Contact;
use App\Models\Account;
use App\Models\User;
use App\Models\UserAccount;
use App\Models\AccountToken;

class AccountRepository
{
    public function create($firstName = '', $lastName = '', $email = '', $password = '')
    {
        $account = new Account();
        $account->ip = Request::getClientIp();
        $account->account_key = str_random(RANDOM_KEY_LENGTH);

        // Track referal code
        if ($referralCode = Session::get(SESSION_REFERRAL_CODE)) {
            if ($user = User::whereReferralCode($referralCode)->first()) {
                $account->referral_user_id = $user->id;
            }
        }

        if ($locale = Session::get(SESSION_LOCALE)) {
            if ($language = Language::whereLocale($locale)->first()) {
                $account->language_id = $language->id;
            }
        }

        $account->save();

        $user = new User();
        if (!$firstName && !$lastName && !$email && !$password) {
            $user->password = str_random(RANDOM_KEY_LENGTH);
            $user->username = str_random(RANDOM_KEY_LENGTH);
        } else {
            $user->first_name = $firstName;
            $user->last_name = $lastName;
            $user->email = $user->username = $email;
            if (!$password) {
                $password = str_random(RANDOM_KEY_LENGTH);
            }
            $user->password = bcrypt($password);
        }

        $user->confirmed = !Utils::isNinja();
        $user->registered = !Utils::isNinja() && $user->email;

        if (!$user->confirmed) {
            $user->confirmation_code = str_random(RANDOM_KEY_LENGTH);
        }

        $account->users()->save($user);

        return $account;
    }

    public function getSearchData()
    {
        $data = $this->getAccountSearchData();

        $data['navigation'] = $this->getNavigationSearchData();

        return $data;
    }

    private function getAccountSearchData()
    {
        $data = [
            'clients' => [],
            'contacts' => [],
            'invoices' => [],
            'quotes' => [],
        ];

        $clients = Client::scope()
                    ->with('contacts', 'invoices')
                    ->get();

        foreach ($clients as $client) {
            if ($client->name) {
                $data['clients'][] = [
                    'value' => $client->name,
                    'url' => $client->present()->url,
                ];
            }

            foreach ($client->contacts as $contact) {
                if ($contact->getFullName()) {
                    $data['contacts'][] = [
                        'value' => $contact->getDisplayName(),
                        'url' => $client->present()->url,
                    ];
                }
                if ($contact->email) {
                    $data[trans('texts.contacts')][] = [
                        'value' => $contact->email,
                        'url' => $client->present()->url,
                    ];
                }
            }

            foreach ($client->invoices as $invoice) {
                $entityType = $invoice->getEntityType();
                $data["{$entityType}s"][] = [
                    'value' => $invoice->getDisplayName() . ': ' . $client->getDisplayName(),
                    'url' => $invoice->present()->url,
                ];
            }
        }

        return $data;
    }

    private function getNavigationSearchData()
    {
        $entityTypes = [
            ENTITY_INVOICE,
            ENTITY_CLIENT,
            ENTITY_QUOTE,
            ENTITY_TASK,
            ENTITY_EXPENSE,
            ENTITY_RECURRING_INVOICE,
            ENTITY_PAYMENT,
            ENTITY_CREDIT
        ];

        foreach ($entityTypes as $entityType) {
            $features[] = [
                "new_{$entityType}",
                "/{$entityType}s/create",
            ];
            $features[] = [
                "list_{$entityType}s",
                "/{$entityType}s",
            ];
        }

        $features[] = ['dashboard', '/dashboard'];
        $features[] = ['customize_design', '/settings/customize_design'];
        $features[] = ['new_tax_rate', '/tax_rates/create'];
        $features[] = ['new_product', '/products/create'];
        $features[] = ['new_user', '/users/create'];

        $settings = array_merge(Account::$basicSettings, Account::$advancedSettings);

        foreach ($settings as $setting) {
            $features[] = [
                $setting,
                "/settings/{$setting}",
            ];
        }

        foreach ($features as $feature) {
            $data[] = [
                'value' => trans('texts.' . $feature[0]),
                'url' => URL::to($feature[1])
            ];
        }

        return $data;
    }

    public function enableProPlan()
    {
        if (Auth::user()->isPro() && ! Auth::user()->isTrial()) {
            return false;
        }
        
        $account = Auth::user()->account;
        $client = $this->getNinjaClient($account);
        $invitation = $this->createNinjaInvoice($client, $account);

        return $invitation;
    }

    public function createNinjaInvoice($client, $account)
    {
        $account = $this->getNinjaAccount();
        $lastInvoice = Invoice::withTrashed()->whereAccountId($account->id)->orderBy('public_id', 'DESC')->first();
        $publicId = $lastInvoice ? ($lastInvoice->public_id + 1) : 1;

        $invoice = new Invoice();
        $invoice->account_id = $account->id;
        $invoice->user_id = $account->users()->first()->id;
        $invoice->public_id = $publicId;
        $invoice->client_id = $client->id;
        $invoice->invoice_number = $account->getNextInvoiceNumber($invoice);
        $invoice->invoice_date = $account->getRenewalDate();
        $invoice->amount = PRO_PLAN_PRICE;
        $invoice->balance = PRO_PLAN_PRICE;
        $invoice->save();

        $item = new InvoiceItem();
        $item->account_id = $account->id;
        $item->user_id = $account->users()->first()->id;
        $item->public_id = $publicId;
        $item->qty = 1;
        $item->cost = PRO_PLAN_PRICE;
        $item->notes = trans('texts.pro_plan_description');
        $item->product_key = trans('texts.pro_plan_product');
        $invoice->invoice_items()->save($item);

        $invitation = new Invitation();
        $invitation->account_id = $account->id;
        $invitation->user_id = $account->users()->first()->id;
        $invitation->public_id = $publicId;
        $invitation->invoice_id = $invoice->id;
        $invitation->contact_id = $client->contacts()->first()->id;
        $invitation->invitation_key = str_random(RANDOM_KEY_LENGTH);
        $invitation->save();

        return $invitation;
    }

    public function getNinjaAccount()
    {
        $account = Account::whereAccountKey(NINJA_ACCOUNT_KEY)->first();

        if ($account) {
            return $account;
        } else {
            $account = new Account();
            $account->name = 'Invoice Ninja';
            $account->work_email = 'contact@invoiceninja.com';
            $account->work_phone = '(800) 763-1948';
            $account->account_key = NINJA_ACCOUNT_KEY;
            $account->save();

            $random = str_random(RANDOM_KEY_LENGTH);
            $user = new User();
            $user->registered = true;
            $user->confirmed = true;
            $user->email = 'contact@invoiceninja.com';
            $user->password = $random;
            $user->username = $random;
            $user->first_name = 'Invoice';
            $user->last_name = 'Ninja';
            $user->notify_sent = true;
            $user->notify_paid = true;
            $account->users()->save($user);

            $accountGateway = new AccountGateway();
            $accountGateway->user_id = $user->id;
            $accountGateway->gateway_id = NINJA_GATEWAY_ID;
            $accountGateway->public_id = 1;
            $accountGateway->setConfig(json_decode(env(NINJA_GATEWAY_CONFIG)));
            $account->account_gateways()->save($accountGateway);
        }

        return $account;
    }

    public function getNinjaClient($account)
    {
        $account->load('users');
        $ninjaAccount = $this->getNinjaAccount();
        $client = Client::whereAccountId($ninjaAccount->id)->wherePublicId($account->id)->first();

        if (!$client) {
            $client = new Client();
            $client->public_id = $account->id;
            $client->user_id = $ninjaAccount->users()->first()->id;
            $client->currency_id = 1;
            foreach (['name', 'address1', 'address2', 'city', 'state', 'postal_code', 'country_id', 'work_phone', 'language_id'] as $field) {
                $client->$field = $account->$field;
            }
            $ninjaAccount->clients()->save($client);

            $contact = new Contact();
            $contact->user_id = $ninjaAccount->users()->first()->id;
            $contact->account_id = $ninjaAccount->id;
            $contact->public_id = $account->id;
            $contact->is_primary = true;
            foreach (['first_name', 'last_name', 'email', 'phone'] as $field) {
                $contact->$field = $account->users()->first()->$field;
            }
            $client->contacts()->save($contact);
        }

        return $client;
    }

    public function findByKey($key)
    {
        $account = Account::whereAccountKey($key)
                    ->with('clients.invoices.invoice_items', 'clients.contacts')
                    ->firstOrFail();

        return $account;
    }

    public function unlinkUserFromOauth($user)
    {
        $user->oauth_provider_id = null;
        $user->oauth_user_id = null;
        $user->save();
    }

    public function updateUserFromOauth($user, $firstName, $lastName, $email, $providerId, $oauthUserId)
    {
        if (!$user->registered) {
            $rules = ['email' => 'email|required|unique:users,email,'.$user->id.',id'];
            $validator = Validator::make(['email' => $email], $rules);
            if ($validator->fails()) {
                $messages = $validator->messages();
                return $messages->first('email');
            }

            $user->email = $email;
            $user->first_name = $firstName;
            $user->last_name = $lastName;
            $user->registered = true;

            $user->account->startTrial();
        }

        $user->oauth_provider_id = $providerId;
        $user->oauth_user_id = $oauthUserId;
        $user->save();

        return true;
    }

    public function registerNinjaUser($user)
    {
        if ($user->email == TEST_USERNAME) {
            return false;
        }

        $url = (Utils::isNinjaDev() ? SITE_URL : NINJA_APP_URL) . '/signup/register';
        $data = '';
        $fields = [
            'first_name' => urlencode($user->first_name),
            'last_name' => urlencode($user->last_name),
            'email' => urlencode($user->email),
        ];

        foreach ($fields as $key => $value) {
            $data .= $key.'='.$value.'&';
        }
        rtrim($data, '&');

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, count($fields));
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_exec($ch);
        curl_close($ch);
    }

    public function findUserByOauth($providerId, $oauthUserId)
    {
        return User::where('oauth_user_id', $oauthUserId)
                    ->where('oauth_provider_id', $providerId)
                    ->first();
    }

    public function findUsers($user, $with = null)
    {
        $accounts = $this->findUserAccounts($user->id);

        if ($accounts) {
            return $this->getUserAccounts($accounts, $with);
        } else {
            return [$user];
        }
    }

    public function findUserAccounts($userId1, $userId2 = false)
    {
        if (!Schema::hasTable('user_accounts')) {
            return false;
        }

        $query = UserAccount::where('user_id1', '=', $userId1)
                                ->orWhere('user_id2', '=', $userId1)
                                ->orWhere('user_id3', '=', $userId1)
                                ->orWhere('user_id4', '=', $userId1)
                                ->orWhere('user_id5', '=', $userId1);

        if ($userId2) {
            $query->orWhere('user_id1', '=', $userId2)
                    ->orWhere('user_id2', '=', $userId2)
                    ->orWhere('user_id3', '=', $userId2)
                    ->orWhere('user_id4', '=', $userId2)
                    ->orWhere('user_id5', '=', $userId2);
        }

        return $query->first(['id', 'user_id1', 'user_id2', 'user_id3', 'user_id4', 'user_id5']);
    }

    public function getUserAccounts($record, $with = null)
    {
        if (!$record) {
            return false;
        }

        $userIds = [];
        for ($i=1; $i<=5; $i++) {
            $field = "user_id$i";
            if ($record->$field) {
                $userIds[] = $record->$field;
            }
        }

        $users = User::with('account')
                    ->whereIn('id', $userIds);

        if ($with) {
            $users->with($with);
        }
        
        return $users->get();
    }

    public function prepareUsersData($record)
    {
        if (!$record) {
            return false;
        }

        $users = $this->getUserAccounts($record);

        $data = [];
        foreach ($users as $user) {
            $item = new stdClass();
            $item->id = $record->id;
            $item->user_id = $user->id;
            $item->user_name = $user->getDisplayName();
            $item->account_id = $user->account->id;
            $item->account_name = $user->account->getDisplayName();
            $item->pro_plan_paid = $user->account->pro_plan_paid;
            $item->logo_path = $user->account->hasLogo() ? $user->account->getLogoPath() : null;
            $data[] = $item;
        }

        return $data;
    }

    public function loadAccounts($userId) {
        $record = self::findUserAccounts($userId);
        return self::prepareUsersData($record);
    }

    public function syncAccounts($userId, $proPlanPaid) {
        $users = self::loadAccounts($userId);
        self::syncUserAccounts($users, $proPlanPaid);
    }

    public function syncUserAccounts($users, $proPlanPaid = false) {
        if (!$users) {
            return;
        }

        if (!$proPlanPaid) {
            foreach ($users as $user) {
                if ($user->pro_plan_paid && $user->pro_plan_paid != '0000-00-00') {
                    $proPlanPaid = $user->pro_plan_paid;
                    break;
                }
            }
        }

        if (!$proPlanPaid) {
            return;
        }

        $accountIds = [];
        foreach ($users as $user) {
            if ($user->pro_plan_paid != $proPlanPaid) {
                $accountIds[] = $user->account_id;
            }
        }

        if (count($accountIds)) {
            DB::table('accounts')
                ->whereIn('id', $accountIds)
                ->update(['pro_plan_paid' => $proPlanPaid]);
        }
    }

    public function associateAccounts($userId1, $userId2) {

        $record = self::findUserAccounts($userId1, $userId2);

        if ($record) {
            foreach ([$userId1, $userId2] as $userId) {
                if (!$record->hasUserId($userId)) {
                    $record->setUserId($userId);
                }
            }
        } else {
            $record = new UserAccount();
            $record->user_id1 = $userId1;
            $record->user_id2 = $userId2;
        }

        $record->save();

        $users = self::prepareUsersData($record);
        self::syncUserAccounts($users);

        return $users;
    }

    public function unlinkAccount($account) {
        foreach ($account->users as $user) {
            if ($userAccount = self::findUserAccounts($user->id)) {
                $userAccount->removeUserId($user->id);
                $userAccount->save();
            }
        }
    }

    public function unlinkUser($userAccountId, $userId) {
        $userAccount = UserAccount::whereId($userAccountId)->first();
        if ($userAccount->hasUserId($userId)) {
            $userAccount->removeUserId($userId);
            $userAccount->save();
        }
    }

    public function findWithReminders()
    {
        return Account::whereRaw('enable_reminder1 = 1 OR enable_reminder2 = 1 OR enable_reminder3 = 1')->get();
    }

    public function getReferralCode()
    {
        do {
            $code = strtoupper(str_random(8));
            $match = User::whereReferralCode($code)
                        ->withTrashed()
                        ->first();
        } while ($match);
        
        return $code;
    }

    public function createTokens($user, $name)
    {
        $name = trim($name) ?: 'TOKEN';
        $users = $this->findUsers($user);

        foreach ($users as $user) {
            if ($token = AccountToken::whereUserId($user->id)->whereName($name)->first()) {
                continue;
            }

            $token = AccountToken::createNew($user);
            $token->name = $name;
            $token->token = str_random(RANDOM_KEY_LENGTH);
            $token->save();
        }
    }

    public function getUserAccountId($account)
    {
        $user = $account->users()->first();
        $userAccount = $this->findUserAccounts($user->id);

        return $userAccount ? $userAccount->id : false;
    }

    public function save($data, $account)
    {
        $account->fill($data);
        $account->save();
    }
}
