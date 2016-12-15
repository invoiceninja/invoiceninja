<?php namespace App\Ninja\Repositories;

use Auth;
use Request;
use Input;
use Session;
use Utils;
use URL;
use stdClass;
use Validator;
use Schema;
use App\Models\AccountGateway;
use App\Models\Invitation;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Client;
use App\Models\Credit;
use App\Models\Language;
use App\Models\Contact;
use App\Models\Account;
use App\Models\Company;
use App\Models\User;
use App\Models\UserAccount;
use App\Models\AccountToken;

class AccountRepository
{
    public function create($firstName = '', $lastName = '', $email = '', $password = '')
    {
        $company = new Company();
        $company->utm_source = Input::get('utm_source');
        $company->utm_medium = Input::get('utm_medium');
        $company->utm_campaign = Input::get('utm_campaign');
        $company->utm_term = Input::get('utm_term');
        $company->utm_content = Input::get('utm_content');
        $company->save();

        $account = new Account();
        $account->ip = Request::getClientIp();
        $account->account_key = str_random(RANDOM_KEY_LENGTH);
        $account->company_id = $company->id;

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
        $user->registered = !Utils::isNinja() || $email;

        if (!$user->confirmed) {
            $user->confirmation_code = str_random(RANDOM_KEY_LENGTH);
        }

        $account->users()->save($user);

        return $account;
    }

    public function getSearchData($user)
    {
        $data = $this->getAccountSearchData($user);

        $data['navigation'] = $user->is_admin ? $this->getNavigationSearchData() : [];

        return $data;
    }

    private function getAccountSearchData($user)
    {
        $account = $user->account;

        $data = [
            'clients' => [],
            'contacts' => [],
            'invoices' => [],
            'quotes' => [],
        ];

        // include custom client fields in search
        if ($account->custom_client_label1) {
            $data[$account->custom_client_label1] = [];
        }
        if ($account->custom_client_label2) {
            $data[$account->custom_client_label2] = [];
        }

        if ($user->hasPermission('view_all')) {
            $clients = Client::scope()
                        ->with('contacts', 'invoices')
                        ->get();
        } else {
            $clients = Client::scope()
                        ->where('user_id', '=', $user->id)
                        ->with(['contacts', 'invoices' => function($query) use ($user) {
                            $query->where('user_id', '=', $user->id);
                        }])->get();
        }

        foreach ($clients as $client) {
            if ($client->name) {
                $data['clients'][] = [
                    'value' => $client->name,
                    'tokens' => $client->name,
                    'url' => $client->present()->url,
                ];
            }

            if ($client->custom_value1) {
                $data[$account->custom_client_label1][] = [
                    'value' => "{$client->custom_value1}: " . $client->getDisplayName(),
                    'tokens' => $client->custom_value1,
                    'url' => $client->present()->url,
                ];
            }
            if ($client->custom_value2) {
                $data[$account->custom_client_label2][] = [
                    'value' => "{$client->custom_value2}: " . $client->getDisplayName(),
                    'tokens' => $client->custom_value2,
                    'url' => $client->present()->url,
                ];
            }

            foreach ($client->contacts as $contact) {
                if ($contact->getFullName()) {
                    $data['contacts'][] = [
                        'value' => $contact->getDisplayName(),
                        'tokens' => $contact->getDisplayName(),
                        'url' => $client->present()->url,
                    ];
                }
                if ($contact->email) {
                    $data['contacts'][] = [
                        'value' => $contact->email,
                        'tokens' => $contact->email,
                        'url' => $client->present()->url,
                    ];
                }
            }

            foreach ($client->invoices as $invoice) {
                $entityType = $invoice->getEntityType();
                $data["{$entityType}s"][] = [
                    'value' => $invoice->getDisplayName() . ': ' . $client->getDisplayName(),
                    'tokens' => $invoice->getDisplayName() . ': ' . $client->getDisplayName(),
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
            ENTITY_EXPENSE_CATEGORY,
            ENTITY_VENDOR,
            ENTITY_RECURRING_INVOICE,
            ENTITY_PAYMENT,
            ENTITY_CREDIT,
            ENTITY_PROJECT,
        ];

        foreach ($entityTypes as $entityType) {
            $features[] = [
                "new_{$entityType}",
                Utils::pluralizeEntityType($entityType) . '/create'
            ];
            $features[] = [
                'list_' . Utils::pluralizeEntityType($entityType),
                Utils::pluralizeEntityType($entityType)
            ];
        }

        $features = array_merge($features, [
            ['dashboard', '/dashboard'],
            ['customize_design', '/settings/customize_design'],
            ['new_tax_rate', '/tax_rates/create'],
            ['new_product', '/products/create'],
            ['new_user', '/users/create'],
            ['custom_fields', '/settings/invoice_settings'],
            ['invoice_number', '/settings/invoice_settings'],
            ['buy_now_buttons', '/settings/client_portal#buy_now'],
            ['invoice_fields', '/settings/invoice_design#invoice_fields'],
        ]);

        $settings = array_merge(Account::$basicSettings, Account::$advancedSettings);

        if ( ! Utils::isNinjaProd()) {
            $settings[] = ACCOUNT_SYSTEM_SETTINGS;
        }

        foreach ($settings as $setting) {
            $features[] = [
                $setting,
                "/settings/{$setting}",
            ];
        }

        foreach ($features as $feature) {
            $data[] = [
                'value' => trans('texts.' . $feature[0]),
                'tokens' => trans('texts.' . $feature[0]),
                'url' => URL::to($feature[1])
            ];
        }

        return $data;
    }

    public function enablePlan($plan, $credit = 0)
    {
        $account = Auth::user()->account;
        $client = $this->getNinjaClient($account);
        $invitation = $this->createNinjaInvoice($client, $account, $plan, $credit);

        return $invitation;
    }

    public function createNinjaCredit($client, $amount)
    {
        $account = $this->getNinjaAccount();

        $lastCredit = Credit::withTrashed()->whereAccountId($account->id)->orderBy('public_id', 'DESC')->first();
        $publicId = $lastCredit ? ($lastCredit->public_id + 1) : 1;

        $credit = new Credit();
        $credit->public_id = $publicId;
        $credit->account_id = $account->id;
        $credit->user_id = $account->users()->first()->id;
        $credit->client_id = $client->id;
        $credit->amount = $amount;
        $credit->save();

        return $credit;
    }

    public function createNinjaInvoice($client, $clientAccount, $plan, $credit = 0)
    {
        $term = $plan['term'];
        $plan_cost = $plan['price'];
        $num_users = $plan['num_users'];
        $plan = $plan['plan'];

        if ($credit < 0) {
            $credit = 0;
        }

        $account = $this->getNinjaAccount();
        $lastInvoice = Invoice::withTrashed()->whereAccountId($account->id)->orderBy('public_id', 'DESC')->first();
        $renewalDate = $clientAccount->getRenewalDate();
        $publicId = $lastInvoice ? ($lastInvoice->public_id + 1) : 1;

        $invoice = new Invoice();
        $invoice->is_public = true;
        $invoice->account_id = $account->id;
        $invoice->user_id = $account->users()->first()->id;
        $invoice->public_id = $publicId;
        $invoice->client_id = $client->id;
        $invoice->invoice_number = $account->getNextInvoiceNumber($invoice);
        $invoice->invoice_date = $renewalDate->format('Y-m-d');
        $invoice->amount = $invoice->balance = $plan_cost - $credit;
        $invoice->invoice_type_id = INVOICE_TYPE_STANDARD;

        // check for promo/discount
        $clientCompany = $clientAccount->company;
        if ($clientCompany->hasActivePromo() || $clientCompany->hasActiveDiscount($renewalDate)) {
            $discount = $invoice->amount * $clientCompany->discount;
            $invoice->discount = $clientCompany->discount * 100;
            $invoice->amount -= $discount;
            $invoice->balance -= $discount;
        }

        $invoice->save();

        if ($credit) {
            $credit_item = InvoiceItem::createNew($invoice);
            $credit_item->qty = 1;
            $credit_item->cost = -$credit;
            $credit_item->notes = trans('texts.plan_credit_description');
            $credit_item->product_key = trans('texts.plan_credit_product');
            $invoice->invoice_items()->save($credit_item);
        }

        $item = InvoiceItem::createNew($invoice);
        $item->qty = 1;
        $item->cost = $plan_cost;
        $item->notes = trans("texts.{$plan}_plan_{$term}_description");

        if ($plan == PLAN_ENTERPRISE) {
            $min = Utils::getMinNumUsers($num_users);
            $item->notes .= "\n\n###" . trans('texts.min_to_max_users', ['min' => $min, 'max' => $num_users]);
        }

        // Don't change this without updating the regex in PaymentService->createPayment()
        $item->product_key = 'Plan - '.ucfirst($plan).' ('.ucfirst($term).')';
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

            if ($config = env(NINJA_GATEWAY_CONFIG)) {
                $accountGateway = new AccountGateway();
                $accountGateway->user_id = $user->id;
                $accountGateway->gateway_id = NINJA_GATEWAY_ID;
                $accountGateway->public_id = 1;
                $accountGateway->setConfig(json_decode($config));
                $account->account_gateways()->save($accountGateway);
            }
        }

        return $account;
    }

    public function getNinjaClient($account)
    {
        $account->load('users');
        $ninjaAccount = $this->getNinjaAccount();
        $ninjaUser = $ninjaAccount->getPrimaryUser();
        $client = Client::whereAccountId($ninjaAccount->id)
                    ->wherePublicId($account->id)
                    ->first();
        $clientExists = $client ? true : false;

        if (!$client) {
            $client = new Client();
            $client->public_id = $account->id;
            $client->account_id = $ninjaAccount->id;
            $client->user_id = $ninjaUser->id;
            $client->currency_id = 1;
        }

        foreach (['name', 'address1', 'address2', 'city', 'state', 'postal_code', 'country_id', 'work_phone', 'language_id', 'vat_number'] as $field) {
            $client->$field = $account->$field;
        }

        $client->save();

        if ($clientExists) {
            $contact = $client->getPrimaryContact();
        } else {
            $contact = new Contact();
            $contact->user_id = $ninjaUser->id;
            $contact->account_id = $ninjaAccount->id;
            $contact->public_id = $account->id;
            $contact->is_primary = true;
        }

        $user = $account->getPrimaryUser();
        foreach (['first_name', 'last_name', 'email', 'phone'] as $field) {
            $contact->$field = $user->$field;
        }

        $client->contacts()->save($contact);

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

            $user->account->startTrial(PLAN_PRO);
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
            $item->public_id = $user->public_id;
            $item->user_name = $user->getDisplayName();
            $item->account_id = $user->account->id;
            $item->account_name = $user->account->getDisplayName();
            $item->logo_url = $user->account->hasLogo() ? $user->account->getLogoUrl() : null;
            $data[] = $item;
        }

        return $data;
    }

    public function loadAccounts($userId) {
        $record = self::findUserAccounts($userId);
        return self::prepareUsersData($record);
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

        $users = $this->getUserAccounts($record);

        // Pick the primary user
        foreach ($users as $user) {
            if (!$user->public_id) {
                $useAsPrimary = false;
                if(empty($primaryUser)) {
                    $useAsPrimary = true;
                }

                $planDetails = $user->account->getPlanDetails(false, false);
                $planLevel = 0;

                if ($planDetails) {
                    $planLevel = 1;
                    if ($planDetails['plan'] == PLAN_ENTERPRISE) {
                        $planLevel = 2;
                    }

                    if (!$useAsPrimary && (
                        $planLevel > $primaryUserPlanLevel
                        || ($planLevel == $primaryUserPlanLevel && $planDetails['expires'] > $primaryUserPlanExpires)
                    )) {
                        $useAsPrimary = true;
                    }
                }

                if  ($useAsPrimary) {
                    $primaryUser = $user;
                    $primaryUserPlanLevel = $planLevel;
                    if ($planDetails) {
                        $primaryUserPlanExpires = $planDetails['expires'];
                    }
                }
            }
        }

        // Merge other companies into the primary user's company
        if (!empty($primaryUser)) {
            foreach ($users as $user) {
                if ($user == $primaryUser || $user->public_id) {
                    continue;
                }

                if ($user->account->company_id != $primaryUser->account->company_id) {
                    foreach ($user->account->company->accounts as $account) {
                        $account->company_id = $primaryUser->account->company_id;
                        $account->save();
                    }
                    $user->account->company->forceDelete();
                }
            }
        }

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

        $user = User::whereId($userId)->first();

        if (!$user->public_id && $user->account->company->accounts->count() > 1) {
            $company = Company::create();
            $company->save();
            $user->account->company_id = $company->id;
            $user->account->save();
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
