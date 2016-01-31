<?php namespace App\Http\Controllers;

use Auth;
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
use Request;
use App\Models\Affiliate;
use App\Models\License;
use App\Models\User;
use App\Models\Account;
use App\Models\Gateway;
use App\Models\InvoiceDesign;
use App\Models\TaxRate;
use App\Models\PaymentTerm;
use App\Ninja\Repositories\AccountRepository;
use App\Ninja\Repositories\ReferralRepository;
use App\Ninja\Mailers\UserMailer;
use App\Ninja\Mailers\ContactMailer;
use App\Events\UserLoggedIn;
use App\Events\UserSettingsChanged;
use App\Services\AuthService;

class AccountController extends BaseController
{
    protected $accountRepo;
    protected $userMailer;
    protected $contactMailer;
    protected $referralRepository;

    public function __construct(AccountRepository $accountRepo, UserMailer $userMailer, ContactMailer $contactMailer, ReferralRepository $referralRepository)
    {
        parent::__construct();

        $this->accountRepo = $accountRepo;
        $this->userMailer = $userMailer;
        $this->contactMailer = $contactMailer;
        $this->referralRepository = $referralRepository;
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
        event(new UserLoggedIn());

        $redirectTo = Input::get('redirect_to') ?: 'invoices/create';

        return Redirect::to($redirectTo)->with('sign_up', Input::get('sign_up'));
    }

    public function enableProPlan()
    {
        $invitation = $this->accountRepo->enableProPlan();

        return $invitation->invitation_key;
    }

    public function setTrashVisible($entityType, $visible)
    {
        Session::put("show_trash:{$entityType}", $visible == 'true');

        if ($entityType == 'user') {
            return Redirect::to('settings/'.ACCOUNT_USER_MANAGEMENT);
        } elseif ($entityType == 'token') {
            return Redirect::to('settings/'.ACCOUNT_API_TOKENS);
        } else {
            return Redirect::to("{$entityType}s");
        }
    }

    public function getSearchData()
    {
        $data = $this->accountRepo->getSearchData();

        return Response::json($data);
    }

    public function showSection($section = false)
    {
        if (!$section) {
            return Redirect::to('/settings/'.ACCOUNT_COMPANY_DETAILS, 301);
        }

        if ($section == ACCOUNT_COMPANY_DETAILS) {
            return self::showCompanyDetails();
        } elseif ($section == ACCOUNT_USER_DETAILS) {
            return self::showUserDetails();
        } elseif ($section == ACCOUNT_LOCALIZATION) {
            return self::showLocalization();
        } elseif ($section == ACCOUNT_PAYMENTS) {
            return self::showOnlinePayments();
        } elseif ($section == ACCOUNT_BANKS) {
            return self::showBankAccounts();
        } elseif ($section == ACCOUNT_INVOICE_SETTINGS) {
            return self::showInvoiceSettings();
        } elseif ($section == ACCOUNT_IMPORT_EXPORT) {
            return View::make('accounts.import_export', ['title' => trans('texts.import_export')]);
        } elseif ($section == ACCOUNT_INVOICE_DESIGN || $section == ACCOUNT_CUSTOMIZE_DESIGN) {
            return self::showInvoiceDesign($section);
        } elseif ($section == ACCOUNT_CLIENT_PORTAL) {
            return self::showClientViewStyling();
        } elseif ($section === ACCOUNT_TEMPLATES_AND_REMINDERS) {
            return self::showTemplates();
        } elseif ($section === ACCOUNT_PRODUCTS) {
            return self::showProducts();
        } elseif ($section === ACCOUNT_TAX_RATES) {
            return self::showTaxRates();
        } elseif ($section === ACCOUNT_PAYMENT_TERMS) {
            return self::showPaymentTerms();
        } elseif ($section === ACCOUNT_SYSTEM_SETTINGS) {
            return self::showSystemSettings();
        } else {
            $data = [
                'account' => Account::with('users')->findOrFail(Auth::user()->account_id),
                'title' => trans("texts.{$section}"),
                'section' => $section,
            ];

            return View::make("accounts.{$section}", $data);
        }
    }

    private function showSystemSettings()
    {
        if (Utils::isNinjaProd()) {
            return Redirect::to('/');
        }

        $data = [
            'account' => Account::with('users')->findOrFail(Auth::user()->account_id),
            'title' => trans("texts.system_settings"),
            'section' => ACCOUNT_SYSTEM_SETTINGS,
        ];

        return View::make("accounts.system_settings", $data);
    }

    private function showInvoiceSettings()
    {
        $account = Auth::user()->account;
        $recurringHours = [];

        for ($i = 0; $i<24; $i++) {
            if ($account->military_time) {
                $format = 'H:i';
            } else {
                $format = 'g:i a';
            }
            $recurringHours[$i] = date($format, strtotime("{$i}:00"));
        }

        $data = [
            'account' => Account::with('users')->findOrFail(Auth::user()->account_id),
            'title' => trans("texts.invoice_settings"),
            'section' => ACCOUNT_INVOICE_SETTINGS,
            'recurringHours' => $recurringHours,
        ];

        return View::make("accounts.invoice_settings", $data);
    }

    private function showCompanyDetails()
    {
        // check that logo is less than the max file size
        $account = Auth::user()->account;
        if ($account->isLogoTooLarge()) {
            Session::flash('warning', trans('texts.logo_too_large', ['size' => $account->getLogoSize().'KB']));
        }

        $data = [
            'account' => Account::with('users')->findOrFail(Auth::user()->account_id),
            'countries' => Cache::get('countries'),
            'sizes' => Cache::get('sizes'),
            'industries' => Cache::get('industries'),
            'title' => trans('texts.company_details'),
        ];

        return View::make('accounts.details', $data);
    }

    private function showUserDetails()
    {
        $oauthLoginUrls = [];
        foreach (AuthService::$providers as $provider) {
            $oauthLoginUrls[] = ['label' => $provider, 'url' => '/auth/'.strtolower($provider)];
        }

        $data = [
            'account' => Account::with('users')->findOrFail(Auth::user()->account_id),
            'title' => trans('texts.user_details'),
            'user' => Auth::user(),
            'oauthProviderName' => AuthService::getProviderName(Auth::user()->oauth_provider_id),
            'oauthLoginUrls' => $oauthLoginUrls,
            'referralCounts' => $this->referralRepository->getCounts(Auth::user()->id),
        ];

        return View::make('accounts.user_details', $data);
    }

    private function showLocalization()
    {
        $data = [
            'account' => Account::with('users')->findOrFail(Auth::user()->account_id),
            'timezones' => Cache::get('timezones'),
            'dateFormats' => Cache::get('dateFormats'),
            'datetimeFormats' => Cache::get('datetimeFormats'),
            'currencies' => Cache::get('currencies'),
            'languages' => Cache::get('languages'),
            'title' => trans('texts.localization'),
        ];

        return View::make('accounts.localization', $data);
    }

    private function showBankAccounts()
    {
        $account = Auth::user()->account;
        $account->load('bank_accounts');
        $count = count($account->bank_accounts);

        if ($count == 0) {
            return Redirect::to('bank_accounts/create');
        } else {
            return View::make('accounts.banks', [
                'title' => trans('texts.bank_accounts')
            ]);
        }
    }

    private function showOnlinePayments()
    {
        $account = Auth::user()->account;
        $account->load('account_gateways');
        $count = count($account->account_gateways);

        if ($accountGateway = $account->getGatewayConfig(GATEWAY_STRIPE)) {
            if (! $accountGateway->getPublishableStripeKey()) {
                Session::flash('warning', trans('texts.missing_publishable_key'));
            }
        }

        if ($count == 0) {
            return Redirect::to('gateways/create');
        } else {
            return View::make('accounts.payments', [
                'showAdd' => $count < count(Gateway::$paymentTypes),
                'title' => trans('texts.online_payments')
            ]);
        }
    }

    private function showProducts()
    {
        $columns = ['product', 'description', 'unit_cost'];
        if (Auth::user()->account->invoice_item_taxes) {
            $columns[] = 'tax_rate';
        }
        $columns[] = 'action';

        $data = [
            'account' => Auth::user()->account,
            'title' => trans('texts.product_library'),
            'columns' => Utils::trans($columns),
        ];

        return View::make('accounts.products', $data);
    }

    private function showTaxRates()
    {
        $data = [
            'account' => Auth::user()->account,
            'title' => trans('texts.tax_rates'),
            'taxRates' => TaxRate::scope()->get(['id', 'name', 'rate']),
        ];

        return View::make('accounts.tax_rates', $data);
    }

    private function showPaymentTerms()
    {
        $data = [
            'account' => Auth::user()->account,
            'title' => trans('texts.payment_terms'),
            'taxRates' => PaymentTerm::scope()->get(['id', 'name', 'num_days']),
        ];

        return View::make('accounts.payment_terms', $data);
    }

    private function showInvoiceDesign($section)
    {
        $account = Auth::user()->account->load('country');
        $invoice = new stdClass();
        $client = new stdClass();
        $contact = new stdClass();
        $invoiceItem = new stdClass();

        $client->name = 'Sample Client';
        $client->address1 = trans('texts.address1');
        $client->city = trans('texts.city');
        $client->state = trans('texts.state');
        $client->postal_code = trans('texts.postal_code');
        $client->work_phone = trans('texts.work_phone');
        $client->work_email = trans('texts.work_id');
        
        $invoice->invoice_number = '0000';
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
        $data['invoiceFonts'] = Cache::get('fonts');
        $data['section'] = $section;

        $design = false;
        foreach ($data['invoiceDesigns'] as $item) {
            if ($item->id == $account->invoice_design_id) {
                $design = $item->javascript;
                break;
            }
        }

        if ($section == ACCOUNT_CUSTOMIZE_DESIGN) {
            $data['customDesign'] = ($account->custom_design && !$design) ? $account->custom_design : $design;
        }

        return View::make("accounts.{$section}", $data);
    }

    private function showClientViewStyling()
    {
        $account = Auth::user()->account->load('country');
        $css = $account->client_view_css ? $account->client_view_css : '';

        if (Utils::isNinja() && $css) {
            // Unescape the CSS for display purposes
            $css = str_replace(
                array('\3C ', '\3E ', '\26 '),
                array('<', '>', '&'),
                $css
            );
        }

        $data = [
            'client_view_css' => $css,
            'title' => trans("texts.client_portal"),
            'section' => ACCOUNT_CLIENT_PORTAL,
        ];

        return View::make("accounts.client_portal", $data);
    }

    private function showTemplates()
    {
        $account = Auth::user()->account->load('country');
        $data['account'] = $account;
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

        return View::make('accounts.templates_and_reminders', $data);
    }

    public function doSection($section = ACCOUNT_COMPANY_DETAILS)
    {
        if ($section === ACCOUNT_COMPANY_DETAILS) {
            return AccountController::saveDetails();
        } elseif ($section === ACCOUNT_USER_DETAILS) {
            return AccountController::saveUserDetails();
        } elseif ($section === ACCOUNT_LOCALIZATION) {
            return AccountController::saveLocalization();
        } elseif ($section === ACCOUNT_NOTIFICATIONS) {
            return AccountController::saveNotifications();
        } elseif ($section === ACCOUNT_EXPORT) {
            return AccountController::export();
        } elseif ($section === ACCOUNT_INVOICE_SETTINGS) {
            return AccountController::saveInvoiceSettings();
        } elseif ($section === ACCOUNT_EMAIL_SETTINGS) {
            return AccountController::saveEmailSettings();
        } elseif ($section === ACCOUNT_INVOICE_DESIGN) {
            return AccountController::saveInvoiceDesign();
        } elseif ($section === ACCOUNT_CUSTOMIZE_DESIGN) {
            return AccountController::saveCustomizeDesign();
        } elseif ($section === ACCOUNT_CLIENT_PORTAL) {
            return AccountController::saveClientPortal();
        } elseif ($section === ACCOUNT_TEMPLATES_AND_REMINDERS) {
            return AccountController::saveEmailTemplates();
        } elseif ($section === ACCOUNT_PRODUCTS) {
            return AccountController::saveProducts();
        } elseif ($section === ACCOUNT_TAX_RATES) {
            return AccountController::saveTaxRates();
        } elseif ($section === ACCOUNT_PAYMENT_TERMS) {
            return AccountController::savePaymetTerms();
        }
    }

    private function saveCustomizeDesign()
    {
        if (Auth::user()->account->isPro()) {
            $account = Auth::user()->account;
            $account->custom_design = Input::get('custom_design');
            $account->invoice_design_id = CUSTOM_DESIGN;
            $account->save();

            Session::flash('message', trans('texts.updated_settings'));
        }

        return Redirect::to('settings/'.ACCOUNT_CUSTOMIZE_DESIGN);
    }

    private function saveClientPortal()
    {
        // Only allowed for pro Invoice Ninja users or white labeled self-hosted users
        if ((Utils::isNinja() && Auth::user()->account->isPro()) || Auth::user()->account->isWhiteLabel()) {
            $input_css = Input::get('client_view_css');
            if (Utils::isNinja()) {
                // Allow referencing the body element
                $input_css = preg_replace('/(?<![a-z0-9\-\_\#\.])body(?![a-z0-9\-\_])/i', '.body', $input_css);

                //
                // Inspired by http://stackoverflow.com/a/5209050/1721527, dleavitt <https://stackoverflow.com/users/362110/dleavitt>
                //

                // Create a new configuration object
                $config = \HTMLPurifier_Config::createDefault();
                $config->set('Filter.ExtractStyleBlocks', true);
                $config->set('CSS.AllowImportant', true);
                $config->set('CSS.AllowTricky', true);
                $config->set('CSS.Trusted', true);

                // Create a new purifier instance
                $purifier = new \HTMLPurifier($config);

                // Wrap our CSS in style tags and pass to purifier.
                // we're not actually interested in the html response though
                $html = $purifier->purify('<style>'.$input_css.'</style>');

                // The "style" blocks are stored seperately
                $output_css = $purifier->context->get('StyleBlocks');

                // Get the first style block
                $sanitized_css = count($output_css) ? $output_css[0] : '';
            } else {
                $sanitized_css = $input_css;
            }

            $account = Auth::user()->account;
            $account->client_view_css = $sanitized_css;
            $account->save();

            Session::flash('message', trans('texts.updated_settings'));
        }

        return Redirect::to('settings/'.ACCOUNT_CLIENT_PORTAL);
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
                    $account->{"num_days_{$type}"} = Input::get("num_days_{$type}");
                    $account->{"field_{$type}"} = Input::get("field_{$type}");
                    $account->{"direction_{$type}"} = Input::get("field_{$type}") == REMINDER_FIELD_INVOICE_DATE ? REMINDER_DIRECTION_AFTER : Input::get("direction_{$type}");
                }
            }

            $account->save();

            Session::flash('message', trans('texts.updated_settings'));
        }

        return Redirect::to('settings/'.ACCOUNT_TEMPLATES_AND_REMINDERS);
    }

    private function saveTaxRates()
    {
        $account = Auth::user()->account;

        $account->invoice_taxes = Input::get('invoice_taxes') ? true : false;
        $account->invoice_item_taxes = Input::get('invoice_item_taxes') ? true : false;
        $account->show_item_taxes = Input::get('show_item_taxes') ? true : false;
        $account->default_tax_rate_id = Input::get('default_tax_rate_id');
        $account->save();

        Session::flash('message', trans('texts.updated_settings'));

        return Redirect::to('settings/'.ACCOUNT_TAX_RATES);
    }

    private function saveProducts()
    {
        $account = Auth::user()->account;

        $account->fill_products = Input::get('fill_products') ? true : false;
        $account->update_products = Input::get('update_products') ? true : false;
        $account->save();

        Session::flash('message', trans('texts.updated_settings'));

        return Redirect::to('settings/'.ACCOUNT_PRODUCTS);
    }

    private function saveEmailSettings()
    {
        if (Auth::user()->account->isPro()) {
            $rules = [];
            $user = Auth::user();
            $iframeURL = preg_replace('/[^a-zA-Z0-9_\-\:\/\.]/', '', substr(strtolower(Input::get('iframe_url')), 0, MAX_IFRAME_URL_LENGTH));
            $iframeURL = rtrim($iframeURL, "/");

            $subdomain = preg_replace('/[^a-zA-Z0-9_\-\.]/', '', substr(strtolower(Input::get('subdomain')), 0, MAX_SUBDOMAIN_LENGTH));
            if ($iframeURL || !$subdomain || in_array($subdomain, ['www', 'app', 'mail', 'admin', 'blog', 'user', 'contact', 'payment', 'payments', 'billing', 'invoice', 'business', 'owner'])) {
                $subdomain = null;
            }
            if ($subdomain) {
                $rules['subdomain'] = "unique:accounts,subdomain,{$user->account_id},id";
            }

            $validator = Validator::make(Input::all(), $rules);

            if ($validator->fails()) {
                return Redirect::to('settings/'.ACCOUNT_EMAIL_SETTINGS)
                    ->withErrors($validator)
                    ->withInput();
            } else {
                $account = Auth::user()->account;
                $account->subdomain = $subdomain;
                $account->iframe_url = $iframeURL;
                $account->pdf_email_attachment = Input::get('pdf_email_attachment') ? true : false;
                $account->email_design_id = Input::get('email_design_id');

                if (Utils::isNinja()) {
                    $account->enable_email_markup = Input::get('enable_email_markup') ? true : false;
                }

                $account->save();
                Session::flash('message', trans('texts.updated_settings'));
            }
        }

        return Redirect::to('settings/'.ACCOUNT_EMAIL_SETTINGS);
    }

    private function saveInvoiceSettings()
    {
        if (Auth::user()->account->isPro()) {
            $rules = [
                'invoice_number_pattern' => 'has_counter',
                'quote_number_pattern' => 'has_counter',
            ];

            $validator = Validator::make(Input::all(), $rules);

            if ($validator->fails()) {
                return Redirect::to('settings/'.ACCOUNT_INVOICE_SETTINGS)
                    ->withErrors($validator)
                    ->withInput();
            } else {
                $account = Auth::user()->account;
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
                $account->custom_invoice_text_label1 = trim(Input::get('custom_invoice_text_label1'));
                $account->custom_invoice_text_label2 = trim(Input::get('custom_invoice_text_label2'));

                $account->invoice_number_counter = Input::get('invoice_number_counter');
                $account->quote_number_prefix = Input::get('quote_number_prefix');
                $account->share_counter = Input::get('share_counter') ? true : false;
                $account->invoice_terms = Input::get('invoice_terms');
                $account->invoice_footer = Input::get('invoice_footer');
                $account->quote_terms = Input::get('quote_terms');
                $account->auto_convert_quote = Input::get('auto_convert_quote');

                if (Input::has('recurring_hour')) {
                    $account->recurring_hour = Input::get('recurring_hour');
                }

                if (!$account->share_counter) {
                    $account->quote_number_counter = Input::get('quote_number_counter');
                }

                if (Input::get('invoice_number_type') == 'prefix') {
                    $account->invoice_number_prefix = trim(Input::get('invoice_number_prefix'));
                    $account->invoice_number_pattern = null;
                } else {
                    $account->invoice_number_pattern = trim(Input::get('invoice_number_pattern'));
                    $account->invoice_number_prefix = null;
                }

                if (Input::get('quote_number_type') == 'prefix') {
                    $account->quote_number_prefix = trim(Input::get('quote_number_prefix'));
                    $account->quote_number_pattern = null;
                } else {
                    $account->quote_number_pattern = trim(Input::get('quote_number_pattern'));
                    $account->quote_number_prefix = null;
                }

                if (!$account->share_counter
                        && $account->invoice_number_prefix == $account->quote_number_prefix
                        && $account->invoice_number_pattern == $account->quote_number_pattern) {
                    Session::flash('error', trans('texts.invalid_counter'));

                    return Redirect::to('settings/'.ACCOUNT_INVOICE_SETTINGS)->withInput();
                } else {
                    $account->save();
                    Session::flash('message', trans('texts.updated_settings'));
                }
            }
        }

        return Redirect::to('settings/'.ACCOUNT_INVOICE_SETTINGS);
    }

    private function saveInvoiceDesign()
    {
        if (Auth::user()->account->isPro()) {
            $account = Auth::user()->account;
            $account->hide_quantity = Input::get('hide_quantity') ? true : false;
            $account->hide_paid_to_date = Input::get('hide_paid_to_date') ? true : false;
            $account->all_pages_header = Input::get('all_pages_header') ? true : false;
            $account->all_pages_footer = Input::get('all_pages_footer') ? true : false;
            $account->header_font_id = Input::get('header_font_id');
            $account->body_font_id = Input::get('body_font_id');
            $account->primary_color = Input::get('primary_color');
            $account->secondary_color = Input::get('secondary_color');
            $account->invoice_design_id = Input::get('invoice_design_id');

            if (Input::has('font_size')) {
                $account->font_size =  intval(Input::get('font_size'));
            }

            $labels = [];
            foreach (['item', 'description', 'unit_cost', 'quantity', 'line_total', 'terms'] as $field) {
                $labels[$field] = Input::get("labels_{$field}");
            }
            $account->invoice_labels = json_encode($labels);

            $account->save();

            Session::flash('message', trans('texts.updated_settings'));
        }

        return Redirect::to('settings/'.ACCOUNT_INVOICE_DESIGN);
    }

    private function saveNotifications()
    {
        $user = Auth::user();
        $user->notify_sent = Input::get('notify_sent');
        $user->notify_viewed = Input::get('notify_viewed');
        $user->notify_paid = Input::get('notify_paid');
        $user->notify_approved = Input::get('notify_approved');
        $user->save();

        Session::flash('message', trans('texts.updated_settings'));

        return Redirect::to('settings/'.ACCOUNT_NOTIFICATIONS);
    }

    private function saveDetails()
    {
        $rules = array(
            'name' => 'required',
            'logo' => 'sometimes|max:'.MAX_LOGO_FILE_SIZE.'|mimes:jpeg,gif,png',
        );

        $validator = Validator::make(Input::all(), $rules);

        if ($validator->fails()) {
            return Redirect::to('settings/'.ACCOUNT_COMPANY_DETAILS)
                ->withErrors($validator)
                ->withInput();
        } else {
            $account = Auth::user()->account;
            $account->name = trim(Input::get('name'));
            $account->id_number = trim(Input::get('id_number'));
            $account->vat_number = trim(Input::get('vat_number'));
            $account->work_email = trim(Input::get('work_email'));
            $account->website = trim(Input::get('website'));
            $account->work_phone = trim(Input::get('work_phone'));
            $account->address1 = trim(Input::get('address1'));
            $account->address2 = trim(Input::get('address2'));
            $account->city = trim(Input::get('city'));
            $account->state = trim(Input::get('state'));
            $account->postal_code = trim(Input::get('postal_code'));
            $account->country_id = Input::get('country_id') ? Input::get('country_id') : null;
            $account->size_id = Input::get('size_id') ? Input::get('size_id') : null;
            $account->industry_id = Input::get('industry_id') ? Input::get('industry_id') : null;
            $account->email_footer = Input::get('email_footer');
            $account->save();

            /* Logo image file */
            if ($file = Input::file('logo')) {
                $path = Input::file('logo')->getRealPath();
                File::delete('logo/'.$account->account_key.'.jpg');
                File::delete('logo/'.$account->account_key.'.png');

                $mimeType = $file->getMimeType();

                if ($mimeType == 'image/jpeg') {
                    $path = 'logo/'.$account->account_key.'.jpg';
                    $file->move('logo/', $account->account_key.'.jpg');
                } elseif ($mimeType == 'image/png') {
                    $path = 'logo/'.$account->account_key.'.png';
                    $file->move('logo/', $account->account_key.'.png');
                } else {
                    if (extension_loaded('fileinfo')) {
                        $image = Image::make($path);
                        $image->resize(200, 120, function ($constraint) {
                            $constraint->aspectRatio();
                        });
                        $path = 'logo/'.$account->account_key.'.jpg';
                        Image::canvas($image->width(), $image->height(), '#FFFFFF')
                            ->insert($image)->save($path);
                    } else {
                        Session::flash('warning', 'Warning: To support gifs the fileinfo PHP extension needs to be enabled.');
                    }
                }

                // make sure image isn't interlaced
                if (extension_loaded('fileinfo')) {
                    $img = Image::make($path);
                    $img->interlace(false);
                    $img->save();
                }
            }

            event(new UserSettingsChanged());

            Session::flash('message', trans('texts.updated_settings'));

            return Redirect::to('settings/'.ACCOUNT_COMPANY_DETAILS);
        }
    }

    private function saveUserDetails()
    {
        $user = Auth::user();
        $rules = ['email' => 'email|required|unique:users,email,'.$user->id.',id'];
        $validator = Validator::make(Input::all(), $rules);

        if ($validator->fails()) {
            return Redirect::to('settings/'.ACCOUNT_USER_DETAILS)
                ->withErrors($validator)
                ->withInput();
        } else {
            $user->first_name = trim(Input::get('first_name'));
            $user->last_name = trim(Input::get('last_name'));
            $user->username = trim(Input::get('email'));
            $user->email = trim(strtolower(Input::get('email')));
            $user->phone = trim(Input::get('phone'));

            if (Utils::isNinja()) {
                if (Input::get('referral_code') && !$user->referral_code) {
                    $user->referral_code = $this->accountRepo->getReferralCode();
                }
            }
            if (Utils::isNinjaDev()) {
                $user->dark_mode = Input::get('dark_mode') ? true : false;
            }

            $user->save();

            event(new UserSettingsChanged());
            Session::flash('message', trans('texts.updated_settings'));

            return Redirect::to('settings/'.ACCOUNT_USER_DETAILS);
        }
    }

    private function saveLocalization()
    {
        $account = Auth::user()->account;
        $account->timezone_id = Input::get('timezone_id') ? Input::get('timezone_id') : null;
        $account->date_format_id = Input::get('date_format_id') ? Input::get('date_format_id') : null;
        $account->datetime_format_id = Input::get('datetime_format_id') ? Input::get('datetime_format_id') : null;
        $account->currency_id = Input::get('currency_id') ? Input::get('currency_id') : 1; // US Dollar
        $account->language_id = Input::get('language_id') ? Input::get('language_id') : 1; // English
        $account->military_time = Input::get('military_time') ? true : false;
        $account->save();

        event(new UserSettingsChanged());

        Session::flash('message', trans('texts.updated_settings'));

        return Redirect::to('settings/'.ACCOUNT_LOCALIZATION);
    }

    public function removeLogo()
    {
        File::delete('logo/'.Auth::user()->account->account_key.'.jpg');
        File::delete('logo/'.Auth::user()->account->account_key.'.png');

        Session::flash('message', trans('texts.removed_logo'));

        return Redirect::to('settings/'.ACCOUNT_COMPANY_DETAILS);
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

        if (Input::get('go_pro') == 'true') {
            Session::set(REQUESTED_PRO_PLAN, true);
        }

        return "{$user->first_name} {$user->last_name}";
    }

    public function doRegister()
    {
        $affiliate = Affiliate::where('affiliate_key', '=', SELF_HOST_AFFILIATE_KEY)->first();
        $email = trim(Input::get('email'));

        if (!$email || $email == TEST_USERNAME) {
            return RESULT_FAILURE;
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

        return RESULT_SUCCESS;
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

        $user = Auth::user();
        $account = Auth::user()->account;
        \Log::info("Canceled Account: {$account->name} - {$user->email}");

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

        return Redirect::to('/settings/'.ACCOUNT_USER_DETAILS)->with('message', trans('texts.confirmation_resent'));
    }

    public function redirectLegacy($section, $subSection = false)
    {
        if ($section === 'details') {
            $section = ACCOUNT_COMPANY_DETAILS;
        } elseif ($section === 'payments') {
            $section = ACCOUNT_PAYMENTS;
        } elseif ($section === 'advanced_settings') {
            $section = $subSection;
            if ($section === 'token_management') {
                $section = ACCOUNT_API_TOKENS;
            }
        }

        if (!in_array($section, array_merge(Account::$basicSettings, Account::$advancedSettings))) {
            $section = ACCOUNT_COMPANY_DETAILS;
        }

        return Redirect::to("/settings/$section/", 301);
    }
}
