<?php namespace App\Ninja\Repositories;

use Auth;
use Request;
use Session;
use Utils;
use DB;
use stdClass;
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
        $clients = \DB::table('clients')
            ->where('clients.deleted_at', '=', null)
            ->where('clients.account_id', '=', \Auth::user()->account_id)
            ->whereRaw("clients.name <> ''")
            ->select(\DB::raw("'Clients' as type, clients.public_id, clients.name, '' as token"));

        $contacts = \DB::table('clients')
            ->join('contacts', 'contacts.client_id', '=', 'clients.id')
            ->where('clients.deleted_at', '=', null)
            ->where('clients.account_id', '=', \Auth::user()->account_id)
            ->whereRaw("CONCAT(contacts.first_name, contacts.last_name, contacts.email) <> ''")
            ->select(\DB::raw("'Contacts' as type, clients.public_id, CONCAT(contacts.first_name, ' ', contacts.last_name, ' ', contacts.email) as name, '' as token"));

        $invoices = \DB::table('clients')
            ->join('invoices', 'invoices.client_id', '=', 'clients.id')
            ->where('clients.account_id', '=', \Auth::user()->account_id)
            ->where('clients.deleted_at', '=', null)
            ->where('invoices.deleted_at', '=', null)
            ->select(\DB::raw("'Invoices' as type, invoices.public_id, CONCAT(invoices.invoice_number, ': ', clients.name) as name, invoices.invoice_number as token"));

        $data = [];

        foreach ($clients->union($contacts)->union($invoices)->get() as $row) {
            $type = $row->type;

            if (!isset($data[$type])) {
                $data[$type] = [];
            }

            $tokens = explode(' ', $row->name);
            $tokens[] = $type;

            if ($type == 'Invoices') {
                $tokens[] = intVal($row->token).'';
            }

            $data[$type][] = [
                'value' => $row->name,
                'public_id' => $row->public_id,
                'tokens' => $tokens,
            ];
        }

        return $data;
    }

    public function enableProPlan()
    {
        if (Auth::user()->isPro()) {
            return false;
        }
        
        $client = $this->getNinjaClient(Auth::user()->account);
        $invitation = $this->createNinjaInvoice($client);

        return $invitation;
    }

    public function createNinjaInvoice($client)
    {
        $account = $this->getNinjaAccount();
        $lastInvoice = Invoice::withTrashed()->whereAccountId($account->id)->orderBy('public_id', 'DESC')->first();
        $publicId = $lastInvoice ? ($lastInvoice->public_id + 1) : 1;

        $invoice = new Invoice();
        $invoice->account_id = $account->id;
        $invoice->user_id = $account->users()->first()->id;
        $invoice->public_id = $publicId;
        $invoice->client_id = $client->id;
        $invoice->invoice_number = $account->getNextInvoiceNumber();
        $invoice->invoice_date = date_create()->format('Y-m-d');
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
            $accountGateway->config = env(NINJA_GATEWAY_CONFIG);
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

    public function registerUser($user)
    {
        $url = (Utils::isNinjaDev() ? '' : NINJA_APP_URL) . '/signup/register';
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
        curl_exec($ch);
        curl_close($ch);
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

    public function prepareUsersData($record) {
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
                    ->whereIn('id', $userIds)
                    ->get();

        $data = [];
        foreach ($users as $user) {
            $item = new stdClass();
            $item->id = $record->id;
            $item->user_id = $user->id;
            $item->user_name = $user->getDisplayName();
            $item->account_id = $user->account->id;
            $item->account_name = $user->account->getDisplayName();
            $item->pro_plan_paid = $user->account->pro_plan_paid;
            $item->logo_path = file_exists($user->account->getLogoPath()) ? $user->account->getLogoPath() : null;
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
}
