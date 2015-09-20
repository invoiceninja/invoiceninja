<?php namespace App\Http\Controllers;

use Auth;
use Event;
use File;
use Image;
use Input;
use Redirect;
use Session;
use Utils;
use Validator;
use View;
use stdClass;
use Cache;
use Response;
use parseCSV;
use Request;

use App\Models\Affiliate;
use App\Models\License;
use App\Models\User;
use App\Models\Client;
use App\Models\Contact;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Activity;
use App\Models\Payment;
use App\Models\Credit;
use App\Models\Account;
use App\Models\Country;
use App\Models\Currency;
use App\Models\DateFormat;
use App\Models\DatetimeFormat;
use App\Models\Language;
use App\Models\Size;
use App\Models\Gateway;
use App\Models\Timezone;
use App\Models\Industry;
use App\Models\InvoiceDesign;
use App\Ninja\Repositories\AccountRepository;
use App\Ninja\Mailers\UserMailer;
use App\Ninja\Mailers\ContactMailer;
use App\Events\UserLoggedIn;
use App\Events\UserSettingsChanged;

class AccountController extends BaseController
{
    protected $accountRepo;
    protected $userMailer;
    protected $contactMailer;

    public function __construct(AccountRepository $accountRepo, UserMailer $userMailer, ContactMailer $contactMailer)
    {
        parent::__construct();

        $this->accountRepo = $accountRepo;
        $this->userMailer = $userMailer;
        $this->contactMailer = $contactMailer;
    }

    public function demo()
    {
        $demoAccountId = Utils::getDemoAccountId();

        if (!$demoAccountId) {
            return Redirect::to('/');
        }

        $account = Account::find($demoAccountId);
        $user = $account->users()->first();

        Auth::login($user, true);

        return Redirect::to('invoices/create');
    }

    public function getStarted()
    {
        $user = false;
        $guestKey = Input::get('guest_key'); // local storage key to login until registered
        $prevUserId = Session::pull(PREV_USER_ID); // last user id used to link to new account

        if (Auth::check()) {
            return Redirect::to('invoices/create');
        }

        if (!Utils::isNinja() && (Account::count() > 0 && !$prevUserId)) {
            return Redirect::to('/login');
        }
        
        if ($guestKey && !$prevUserId) {
            $user = User::where('password', '=', $guestKey)->first();

            if ($user && $user->registered) {
                return Redirect::to('/');
            }
        }

        if (!$user) {
            $account = $this->accountRepo->create();
            $user = $account->users()->first();

            Session::forget(RECENTLY_VIEWED);

            if ($prevUserId) {
                $users = $this->accountRepo->associateAccounts($user->id, $prevUserId);
                Session::put(SESSION_USER_ACCOUNTS, $users);
            }
        }

        Auth::login($user, true);
        Event::fire(new UserLoggedIn());
        
        $redirectTo = Input::get('redirect_to', 'invoices/create');
        return Redirect::to($redirectTo)->with('sign_up', Input::get('sign_up'));
    }

    public function enableProPlan()
    {
        $invitation = $this->accountRepo->enableProPlan();

        /*
        if ($invoice)
        {
            $this->contactMailer->sendInvoice($invoice);
        }
        */

        return $invitation->invitation_key;
    }

    public function setTrashVisible($entityType, $visible)
    {
        Session::put("show_trash:{$entityType}", $visible == 'true');

        if ($entityType == 'user') {
            return Redirect::to('company/'.ACCOUNT_ADVANCED_SETTINGS.'/'.ACCOUNT_USER_MANAGEMENT);
        } elseif ($entityType == 'token') {
            return Redirect::to('company/'.ACCOUNT_ADVANCED_SETTINGS.'/'.ACCOUNT_TOKEN_MANAGEMENT);
        } else {
            return Redirect::to("{$entityType}s");
        }
    }

    public function getSearchData()
    {
        $data = $this->accountRepo->getSearchData();
        return Response::json($data);
    }

    public function showSection($section = ACCOUNT_DETAILS, $subSection = false)
    {
        if ($section == ACCOUNT_DETAILS) {
            $primaryUser = Auth::user()->account->getPrimaryUser();
            $data = [
                'account' => Account::with('users')->findOrFail(Auth::user()->account_id),
                'countries' => Cache::get('countries'),
                'sizes' => Cache::get('sizes'),
                'industries' => Cache::get('industries'),
                'timezones' => Cache::get('timezones'),
                'dateFormats' => Cache::get('dateFormats'),
                'datetimeFormats' => Cache::get('datetimeFormats'),
                'currencies' => Cache::get('currencies'),
                'languages' => Cache::get('languages'),
                'showUser' => Auth::user()->id === $primaryUser->id,
                'title' => trans('texts.company_details'),
                'primaryUser' => $primaryUser,
            ];

            return View::make('accounts.details', $data);
        } elseif ($section == ACCOUNT_PAYMENTS) {

            $account = Auth::user()->account;
            $account->load('account_gateways');
            $count = count($account->account_gateways);
            
            if ($count == 0) {
                return Redirect::to('gateways/create');
            } else {
                return View::make('accounts.payments', [
                    'showAdd' => $count < count(Gateway::$paymentTypes),
                    'title' => trans('texts.online_payments')
                ]);
            }
        } elseif ($section == ACCOUNT_NOTIFICATIONS) {
            $data = [
                'account' => Account::with('users')->findOrFail(Auth::user()->account_id),
                'title' => trans('texts.notifications'),
            ];

            return View::make('accounts.notifications', $data);
        } elseif ($section == ACCOUNT_IMPORT_EXPORT) {
            return View::make('accounts.import_export', ['title' => trans('texts.import_export')]);
        } elseif ($section == ACCOUNT_ADVANCED_SETTINGS) {
            $account = Auth::user()->account->load('country');
            $data = [
                'account' => $account,
                'feature' => $subSection,
                'title' => trans('texts.invoice_settings'),
            ];

            if ($subSection == ACCOUNT_INVOICE_DESIGN
                    || $subSection == ACCOUNT_CUSTOMIZE_DESIGN) {
                $invoice = new stdClass();
                $client = new stdClass();
                $contact = new stdClass();
                $invoiceItem = new stdClass();

                $client->name = 'Sample Client';
                $client->address1 = '';
                $client->city = '';
                $client->state = '';
                $client->postal_code = '';
                $client->work_phone = '';
                $client->work_email = '';

                $invoice->invoice_number = $account->getNextInvoiceNumber();
                $invoice->invoice_date = Utils::fromSqlDate(date('Y-m-d'));
                $invoice->account = json_decode($account->toJson());
                $invoice->amount = $invoice->balance = 100;

                $invoice->terms = trim($account->invoice_terms);
                $invoice->invoice_footer = trim($account->invoice_footer);

                $contact->email = 'contact@gmail.com';
                $client->contacts = [$contact];

                $invoiceItem->cost = 100;
                $invoiceItem->qty = 1;
                $invoiceItem->notes = 'Notes';
                $invoiceItem->product_key = 'Item';

                $invoice->client = $client;
                $invoice->invoice_items = [$invoiceItem];

                $data['account'] = $account;
                $data['invoice'] = $invoice;
                $data['invoiceLabels'] = json_decode($account->invoice_labels) ?: [];
                $data['title'] = trans('texts.invoice_design');
                $data['invoiceDesigns'] = InvoiceDesign::getDesigns();

                $design = false;
                foreach ($data['invoiceDesigns'] as $item) {
                    if ($item->id == $account->invoice_design_id) {
                        $design = $item->javascript;
                        break;
                    }
                }

                if ($subSection == ACCOUNT_CUSTOMIZE_DESIGN) {
                    $data['customDesign'] = ($account->custom_design && !$design) ? $account->custom_design : $design;
                }
            } else if ($subSection == ACCOUNT_TEMPLATES_AND_REMINDERS) {
                $data['templates'] = [];
                $data['defaultTemplates'] = [];
                foreach ([ENTITY_INVOICE, ENTITY_QUOTE, ENTITY_PAYMENT, REMINDER1, REMINDER2, REMINDER3] as $type) {
                    $data['templates'][$type] = [
                        'subject' => $account->getEmailSubject($type),
                        'template' => $account->getEmailTemplate($type),
                    ];
                    $data['defaultTemplates'][$type] = [
                        'subject' => $account->getDefaultEmailSubject($type),
                        'template' => $account->getDefaultEmailTemplate($type),
                    ];
                }
                $data['emailFooter'] = $account->getEmailFooter();
                $data['title'] = trans('texts.email_templates');
            } else if ($subSection == ACCOUNT_USER_MANAGEMENT) {
                $data['title'] = trans('texts.users_and_tokens');
            }

            return View::make("accounts.{$subSection}", $data);
        } elseif ($section == ACCOUNT_PRODUCTS) {
            $data = [
                'account' => Auth::user()->account,
                'title' => trans('texts.product_library'),
            ];

            return View::make('accounts.products', $data);
        }
    }

    public function doSection($section = ACCOUNT_DETAILS, $subSection = false)
    {
        if ($section == ACCOUNT_DETAILS) {
            return AccountController::saveDetails();
        } elseif ($section == ACCOUNT_IMPORT_EXPORT) {
            return AccountController::importFile();
        } elseif ($section == ACCOUNT_MAP) {
            return AccountController::mapFile();
        } elseif ($section == ACCOUNT_NOTIFICATIONS) {
            return AccountController::saveNotifications();
        } elseif ($section == ACCOUNT_EXPORT) {
            return AccountController::export();
        } elseif ($section == ACCOUNT_ADVANCED_SETTINGS) {
            if ($subSection == ACCOUNT_INVOICE_SETTINGS) {
                return AccountController::saveInvoiceSettings();
            } elseif ($subSection == ACCOUNT_INVOICE_DESIGN) {
                return AccountController::saveInvoiceDesign();
            } elseif ($subSection == ACCOUNT_CUSTOMIZE_DESIGN) {
                return AccountController::saveCustomizeDesign();
            } elseif ($subSection == ACCOUNT_TEMPLATES_AND_REMINDERS) {
                return AccountController::saveEmailTemplates();
            }
        } elseif ($section == ACCOUNT_PRODUCTS) {
            return AccountController::saveProducts();
        }
    }

    private function saveCustomizeDesign() {
        if (Auth::user()->account->isPro()) {
            $account = Auth::user()->account;
            $account->custom_design = Input::get('custom_design');
            $account->invoice_design_id = CUSTOM_DESIGN;
            $account->save();
            
            Session::flash('message', trans('texts.updated_settings'));
        }

        return Redirect::to('company/advanced_settings/customize_design');
    }

    private function saveEmailTemplates()
    {
        if (Auth::user()->account->isPro()) {
            $account = Auth::user()->account;

            foreach ([ENTITY_INVOICE, ENTITY_QUOTE, ENTITY_PAYMENT, REMINDER1, REMINDER2, REMINDER3] as $type) {
                $subjectField = "email_subject_{$type}";
                $subject = Input::get($subjectField, $account->getEmailSubject($type));
                $account->$subjectField = ($subject == $account->getDefaultEmailSubject($type) ? null : $subject);

                $bodyField = "email_template_{$type}";
                $body = Input::get($bodyField, $account->getEmailTemplate($type));
                $account->$bodyField = ($body == $account->getDefaultEmailTemplate($type) ? null : $body);
            }

            foreach ([REMINDER1, REMINDER2, REMINDER3] as $type) {
                $enableField = "enable_{$type}";
                $account->$enableField = Input::get($enableField) ? true : false;

                if ($account->$enableField) {
                    $numDaysField = "num_days_{$type}";
                    $account->$numDaysField = Input::get($numDaysField);
                }
            }

            $account->save();

            Session::flash('message', trans('texts.updated_settings'));
        }
        
        return Redirect::to('company/advanced_settings/templates_and_reminders');
    }

    private function saveProducts()
    {
        $account = Auth::user()->account;

        $account->fill_products = Input::get('fill_products') ? true : false;
        $account->update_products = Input::get('update_products') ? true : false;
        $account->save();

        Session::flash('message', trans('texts.updated_settings'));
        return Redirect::to('company/products');
    }

    private function saveInvoiceSettings()
    {
        if (Auth::user()->account->isPro()) {
            
            $rules = [];
            $user = Auth::user();
            $iframeURL = preg_replace('/[^a-zA-Z0-9_\-\:\/\.]/', '', substr(strtolower(Input::get('iframe_url')), 0, MAX_IFRAME_URL_LENGTH));
            $subdomain = preg_replace('/[^a-zA-Z0-9_\-\.]/', '', substr(strtolower(Input::get('subdomain')), 0, MAX_SUBDOMAIN_LENGTH));
            if (!$subdomain || in_array($subdomain, ['www', 'app', 'mail', 'admin', 'blog', 'user', 'contact', 'payment', 'payments', 'billing', 'invoice', 'business', 'owner'])) {
                $subdomain = null;
            }
            if ($subdomain) {
                $rules['subdomain'] = "unique:accounts,subdomain,{$user->account_id},id";
            }

            $validator = Validator::make(Input::all(), $rules);

            if ($validator->fails()) {
                return Redirect::to('company/details')
                    ->withErrors($validator)
                    ->withInput();
            } else {
                $account = Auth::user()->account;
                $account->subdomain = $subdomain;
                $account->iframe_url = $iframeURL;
                $account->custom_label1 = trim(Input::get('custom_label1'));
                $account->custom_value1 = trim(Input::get('custom_value1'));
                $account->custom_label2 = trim(Input::get('custom_label2'));
                $account->custom_value2 = trim(Input::get('custom_value2'));
                $account->custom_client_label1 = trim(Input::get('custom_client_label1'));
                $account->custom_client_label2 = trim(Input::get('custom_client_label2'));
                $account->custom_invoice_label1 = trim(Input::get('custom_invoice_label1'));
                $account->custom_invoice_label2 = trim(Input::get('custom_invoice_label2'));
                $account->custom_invoice_taxes1 = Input::get('custom_invoice_taxes1') ? true : false;
                $account->custom_invoice_taxes2 = Input::get('custom_invoice_taxes2') ? true : false;

                $account->invoice_number_prefix = Input::get('invoice_number_prefix');
                $account->invoice_number_counter = Input::get('invoice_number_counter');
                $account->quote_number_prefix = Input::get('quote_number_prefix');
                $account->share_counter = Input::get('share_counter') ? true : false;

                $account->pdf_email_attachment = Input::get('pdf_email_attachment') ? true : false;
                $account->auto_wrap = Input::get('auto_wrap') ? true : false;

                if (!$account->share_counter) {
                    $account->quote_number_counter = Input::get('quote_number_counter');
                }

                if (!$account->share_counter && $account->invoice_number_prefix == $account->quote_number_prefix) {
                    Session::flash('error', trans('texts.invalid_counter'));

                    return Redirect::to('company/advanced_settings/invoice_settings')->withInput();
                } else {
                    $account->save();
                    Session::flash('message', trans('texts.updated_settings'));
                }
            }
        }

        return Redirect::to('company/advanced_settings/invoice_settings');
    }

    private function saveInvoiceDesign()
    {
        if (Auth::user()->account->isPro()) {
            $account = Auth::user()->account;
            $account->hide_quantity = Input::get('hide_quantity') ? true : false;
            $account->hide_paid_to_date = Input::get('hide_paid_to_date') ? true : false;
            $account->primary_color = Input::get('primary_color');
            $account->secondary_color = Input::get('secondary_color');
            $account->invoice_design_id = Input::get('invoice_design_id');

            if (Input::has('font_size')) {
                $account->font_size =  intval(Input::get('font_size'));
            }

            $labels = [];
            foreach (['item', 'description', 'unit_cost', 'quantity'] as $field) {
                $labels[$field] = trim(Input::get("labels_{$field}"));
            }
            $account->invoice_labels = json_encode($labels);

            $account->save();

            Session::flash('message', trans('texts.updated_settings'));
        }

        return Redirect::to('company/advanced_settings/invoice_design');
    }

    private function export()
    {
        $output = fopen('php://output', 'w') or Utils::fatalError();
        header('Content-Type:application/csv');
        header('Content-Disposition:attachment;filename=export.csv');

        $clients = Client::scope()->get();
        Utils::exportData($output, $clients->toArray());

        $contacts = Contact::scope()->get();
        Utils::exportData($output, $contacts->toArray());

        $invoices = Invoice::scope()->get();
        Utils::exportData($output, $invoices->toArray());

        $invoiceItems = InvoiceItem::scope()->get();
        Utils::exportData($output, $invoiceItems->toArray());

        $payments = Payment::scope()->get();
        Utils::exportData($output, $payments->toArray());

        $credits = Credit::scope()->get();
        Utils::exportData($output, $credits->toArray());

        fclose($output);
        exit;
    }

    private function importFile()
    {
        $data = Session::get('data');
        Session::forget('data');

        $map = Input::get('map');
        $count = 0;
        $hasHeaders = Input::get('header_checkbox');

        $countries = Cache::get('countries');
        $countryMap = [];

        foreach ($countries as $country) {
            $countryMap[strtolower($country->name)] = $country->id;
        }

        foreach ($data as $row) {
            if ($hasHeaders) {
                $hasHeaders = false;
                continue;
            }

            $client = Client::createNew();
            $contact = Contact::createNew();
            $contact->is_primary = true;
            $contact->send_invoice = true;
            $count++;

            foreach ($row as $index => $value) {
                $field = $map[$index];
                $value = trim($value);

                if ($field == Client::$fieldName && !$client->name) {
                    $client->name = $value;
                } elseif ($field == Client::$fieldPhone && !$client->work_phone) {
                    $client->work_phone = $value;
                } elseif ($field == Client::$fieldAddress1 && !$client->address1) {
                    $client->address1 = $value;
                } elseif ($field == Client::$fieldAddress2 && !$client->address2) {
                    $client->address2 = $value;
                } elseif ($field == Client::$fieldCity && !$client->city) {
                    $client->city = $value;
                } elseif ($field == Client::$fieldState && !$client->state) {
                    $client->state = $value;
                } elseif ($field == Client::$fieldPostalCode && !$client->postal_code) {
                    $client->postal_code = $value;
                } elseif ($field == Client::$fieldCountry && !$client->country_id) {
                    $value = strtolower($value);
                    $client->country_id = isset($countryMap[$value]) ? $countryMap[$value] : null;
                } elseif ($field == Client::$fieldNotes && !$client->private_notes) {
                    $client->private_notes = $value;
                } elseif ($field == Contact::$fieldFirstName && !$contact->first_name) {
                    $contact->first_name = $value;
                } elseif ($field == Contact::$fieldLastName && !$contact->last_name) {
                    $contact->last_name = $value;
                } elseif ($field == Contact::$fieldPhone && !$contact->phone) {
                    $contact->phone = $value;
                } elseif ($field == Contact::$fieldEmail && !$contact->email) {
                    $contact->email = strtolower($value);
                }
            }

            $client->save();
            $client->contacts()->save($contact);
            Activity::createClient($client, false);
        }

        $message = Utils::pluralize('created_client', $count);
        Session::flash('message', $message);

        return Redirect::to('clients');
    }

    private function mapFile()
    {
        $file = Input::file('file');

        if ($file == null) {
            Session::flash('error', trans('texts.select_file'));

            return Redirect::to('company/import_export');
        }

        $name = $file->getRealPath();

        require_once app_path().'/Includes/parsecsv.lib.php';
        $csv = new parseCSV();
        $csv->heading = false;
        $csv->auto($name);

        if (count($csv->data) + Client::scope()->count() > Auth::user()->getMaxNumClients()) {
            $message = trans('texts.limit_clients', ['count' => Auth::user()->getMaxNumClients()]);
            Session::flash('error', $message);

            return Redirect::to('company/import_export');
        }

        Session::put('data', $csv->data);

        $headers = false;
        $hasHeaders = false;
        $mapped = array();
        $columns = array('',
            Client::$fieldName,
            Client::$fieldPhone,
            Client::$fieldAddress1,
            Client::$fieldAddress2,
            Client::$fieldCity,
            Client::$fieldState,
            Client::$fieldPostalCode,
            Client::$fieldCountry,
            Client::$fieldNotes,
            Contact::$fieldFirstName,
            Contact::$fieldLastName,
            Contact::$fieldPhone,
            Contact::$fieldEmail,
        );

        if (count($csv->data) > 0) {
            $headers = $csv->data[0];
            foreach ($headers as $title) {
                if (strpos(strtolower($title), 'name') > 0) {
                    $hasHeaders = true;
                    break;
                }
            }

            for ($i = 0; $i<count($headers); $i++) {
                $title = strtolower($headers[$i]);
                $mapped[$i] = '';

                if ($hasHeaders) {
                    $map = array(
                        'first' => Contact::$fieldFirstName,
                        'last' => Contact::$fieldLastName,
                        'email' => Contact::$fieldEmail,
                        'mobile' => Contact::$fieldPhone,
                        'phone' => Client::$fieldPhone,
                        'name|organization' => Client::$fieldName,
                        'street|address|address1' => Client::$fieldAddress1,
                        'street2|address2' => Client::$fieldAddress2,
                        'city' => Client::$fieldCity,
                        'state|province' => Client::$fieldState,
                        'zip|postal|code' => Client::$fieldPostalCode,
                        'country' => Client::$fieldCountry,
                        'note' => Client::$fieldNotes,
                    );

                    foreach ($map as $search => $column) {
                        foreach (explode("|", $search) as $string) {
                            if (strpos($title, 'sec') === 0) {
                                continue;
                            }

                            if (strpos($title, $string) !== false) {
                                $mapped[$i] = $column;
                                break(2);
                            }
                        }
                    }
                }
            }
        }

        $data = array(
            'data' => $csv->data,
            'headers' => $headers,
            'hasHeaders' => $hasHeaders,
            'columns' => $columns,
            'mapped' => $mapped,
        );

        return View::make('accounts.import_map', $data);
    }

    private function saveNotifications()
    {
        $account = Auth::user()->account;
        $account->invoice_terms = Input::get('invoice_terms');
        $account->invoice_footer = Input::get('invoice_footer');
        $account->email_footer = Input::get('email_footer');
        $account->save();

        $user = Auth::user();
        $user->notify_sent = Input::get('notify_sent');
        $user->notify_viewed = Input::get('notify_viewed');
        $user->notify_paid = Input::get('notify_paid');
        $user->notify_approved = Input::get('notify_approved');
        $user->save();

        Session::flash('message', trans('texts.updated_settings'));

        return Redirect::to('company/notifications');
    }

    private function saveDetails()
    {
        $rules = array(
            'name' => 'required',
            'logo' => 'sometimes|max:1024|mimes:jpeg,gif,png',
        );

        $user = Auth::user()->account->users()->orderBy('id')->first();

        if (Auth::user()->id === $user->id) {
            $rules['email'] = 'email|required|unique:users,email,'.$user->id.',id';
        }

        $validator = Validator::make(Input::all(), $rules);

        if ($validator->fails()) {
            return Redirect::to('company/details')
                ->withErrors($validator)
                ->withInput();
        } else {
            $account = Auth::user()->account;
            $account->name = trim(Input::get('name'));
            $account->id_number = trim(Input::get('id_number'));
            $account->vat_number = trim(Input::get('vat_number'));
            $account->work_email = trim(Input::get('work_email'));
            $account->work_phone = trim(Input::get('work_phone'));
            $account->address1 = trim(Input::get('address1'));
            $account->address2 = trim(Input::get('address2'));
            $account->city = trim(Input::get('city'));
            $account->state = trim(Input::get('state'));
            $account->postal_code = trim(Input::get('postal_code'));
            $account->country_id = Input::get('country_id') ? Input::get('country_id') : null;
            $account->size_id = Input::get('size_id') ? Input::get('size_id') : null;
            $account->industry_id = Input::get('industry_id') ? Input::get('industry_id') : null;
            $account->timezone_id = Input::get('timezone_id') ? Input::get('timezone_id') : null;
            $account->date_format_id = Input::get('date_format_id') ? Input::get('date_format_id') : null;
            $account->datetime_format_id = Input::get('datetime_format_id') ? Input::get('datetime_format_id') : null;
            $account->currency_id = Input::get('currency_id') ? Input::get('currency_id') : 1; // US Dollar
            $account->language_id = Input::get('language_id') ? Input::get('language_id') : 1; // English
            $account->military_time = Input::get('military_time') ? true : false;
            $account->save();

            if (Auth::user()->id === $user->id) {
                $user->first_name = trim(Input::get('first_name'));
                $user->last_name = trim(Input::get('last_name'));
                $user->username = trim(Input::get('email'));
                $user->email = trim(strtolower(Input::get('email')));
                $user->phone = trim(Input::get('phone'));
                if (Utils::isNinja()) {
                    if (Input::get('referral_code')) {
                        $user->referral_code = $this->accountRepo->getReferralCode();
                    }
                }
                if (Utils::isNinjaDev()) {
                    $user->dark_mode = Input::get('dark_mode') ? true : false;
                }
                $user->save();
            }
                
            /* Logo image file */
            if ($file = Input::file('logo')) {
                $path = Input::file('logo')->getRealPath();
                File::delete('logo/'.$account->account_key.'.jpg');
                File::delete('logo/'.$account->account_key.'.png');

                $mimeType = $file->getMimeType();

                if ($mimeType == 'image/jpeg') {
                    $file->move('logo/', $account->account_key . '.jpg');
                } else if ($mimeType == 'image/png') {
                    $file->move('logo/', $account->account_key . '.png');
                } else {
                    if (extension_loaded('fileinfo')) {
                        $image = Image::make($path);
                        $image->resize(200, 120, function ($constraint) {
                            $constraint->aspectRatio();
                        });
                        Image::canvas($image->width(), $image->height(), '#FFFFFF')
                            ->insert($image)->save('logo/'.$account->account_key.'.jpg');
                    } else {
                        Session::flash('warning', 'Warning: To support gifs the fileinfo PHP extension needs to be enabled.');
                    }
                }
            }

            Event::fire(new UserSettingsChanged());

            Session::flash('message', trans('texts.updated_settings'));
            return Redirect::to('company/details');
        }
    }

    public function removeLogo()
    {
        File::delete('logo/'.Auth::user()->account->account_key.'.jpg');
        File::delete('logo/'.Auth::user()->account->account_key.'.png');

        Session::flash('message', trans('texts.removed_logo'));

        return Redirect::to('company/details');
    }

    public function checkEmail()
    {
        $email = User::withTrashed()->where('email', '=', Input::get('email'))->where('id', '<>', Auth::user()->id)->first();

        if ($email) {
            return "taken";
        } else {
            return "available";
        }
    }

    public function submitSignup()
    {
        $rules = array(
            'new_first_name' => 'required',
            'new_last_name' => 'required',
            'new_password' => 'required|min:6',
            'new_email' => 'email|required|unique:users,email,'.Auth::user()->id.',id',
        );

        $validator = Validator::make(Input::all(), $rules);

        if ($validator->fails()) {
            return '';
        }

        $user = Auth::user();
        $user->first_name = trim(Input::get('new_first_name'));
        $user->last_name = trim(Input::get('new_last_name'));
        $user->email = trim(strtolower(Input::get('new_email')));
        $user->username = $user->email;
        $user->password = bcrypt(trim(Input::get('new_password')));
        $user->registered = true;
        $user->save();        

        if (Utils::isNinjaProd()) {
            $this->userMailer->sendConfirmation($user);
        }

        $activities = Activity::scope()->get();
        foreach ($activities as $activity) {
            $activity->message = str_replace('Guest', $user->getFullName(), $activity->message);
            $activity->save();
        }

        if (Input::get('go_pro') == 'true') {
            Session::set(REQUESTED_PRO_PLAN, true);
        }

        Session::set(SESSION_COUNTER, -1);

        return "{$user->first_name} {$user->last_name}";
    }

    public function doRegister()
    {
        $affiliate = Affiliate::where('affiliate_key', '=', SELF_HOST_AFFILIATE_KEY)->first();

        $email = trim(Input::get('email'));

        if (!$email || $email == 'user@example.com') {
            return '';
        }

        $license = new License();
        $license->first_name = Input::get('first_name');
        $license->last_name = Input::get('last_name');
        $license->email = $email;
        $license->transaction_reference = Request::getClientIp();
        $license->license_key = Utils::generateLicense();
        $license->affiliate_id = $affiliate->id;
        $license->product_id = PRODUCT_SELF_HOST;
        $license->is_claimed = 1;
        $license->save();

        return '';
    }

    public function cancelAccount()
    {
        if ($reason = trim(Input::get('reason'))) {
            $email = Auth::user()->email;
            $name = Auth::user()->getDisplayName();

            $data = [
                'text' => $reason,
            ];

            $this->userMailer->sendTo(CONTACT_EMAIL, $email, $name, 'Invoice Ninja Feedback [Canceled Account]', 'contact', $data);
        }

        $account = Auth::user()->account;
        $this->accountRepo->unlinkAccount($account);
        $account->forceDelete();

        Auth::logout();
        Session::flush();

        return Redirect::to('/')->with('clearGuestKey', true);
    }

    public function resendConfirmation()
    {
        $user = Auth::user();
        $this->userMailer->sendConfirmation($user);

        return Redirect::to('/company/details')->with('message', trans('texts.confirmation_resent'));
    }
}
