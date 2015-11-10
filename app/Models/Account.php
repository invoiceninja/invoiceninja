<?php namespace App\Models;

use Eloquent;
use Utils;
use Session;
use DateTime;
use Event;
use App;
use App\Events\UserSettingsChanged;
use Illuminate\Database\Eloquent\SoftDeletes;
use Laracasts\Presenter\PresentableTrait;

class Account extends Eloquent
{
    use PresentableTrait;
    use SoftDeletes;

    protected $presenter = 'App\Ninja\Presenters\AccountPresenter';
    protected $dates = ['deleted_at'];
    protected $hidden = ['ip'];

    public static $basicSettings = [
        ACCOUNT_COMPANY_DETAILS,
        ACCOUNT_USER_DETAILS,
        ACCOUNT_LOCALIZATION,
        ACCOUNT_PAYMENTS,
        ACCOUNT_TAX_RATES,
        ACCOUNT_PRODUCTS,
        ACCOUNT_NOTIFICATIONS,
        ACCOUNT_IMPORT_EXPORT,
    ];

    public static $advancedSettings = [
        ACCOUNT_INVOICE_SETTINGS,
        ACCOUNT_INVOICE_DESIGN,
        ACCOUNT_TEMPLATES_AND_REMINDERS,
        ACCOUNT_CHARTS_AND_REPORTS,
        ACCOUNT_DATA_VISUALIZATIONS,
        ACCOUNT_USER_MANAGEMENT,
        ACCOUNT_API_TOKENS,
    ];

    /*
    protected $casts = [
        'invoice_settings' => 'object',
    ];
    */
    public function account_tokens()
    {
        return $this->hasMany('App\Models\AccountToken');
    }

    public function users()
    {
        return $this->hasMany('App\Models\User');
    }

    public function clients()
    {
        return $this->hasMany('App\Models\Client');
    }

    public function invoices()
    {
        return $this->hasMany('App\Models\Invoice');
    }

    public function account_gateways()
    {
        return $this->hasMany('App\Models\AccountGateway');
    }

    public function tax_rates()
    {
        return $this->hasMany('App\Models\TaxRate');
    }

    public function country()
    {
        return $this->belongsTo('App\Models\Country');
    }

    public function timezone()
    {
        return $this->belongsTo('App\Models\Timezone');
    }

    public function language()
    {
        return $this->belongsTo('App\Models\Language');
    }

    public function date_format()
    {
        return $this->belongsTo('App\Models\DateFormat');
    }

    public function datetime_format()
    {
        return $this->belongsTo('App\Models\DatetimeFormat');
    }

    public function size()
    {
        return $this->belongsTo('App\Models\Size');
    }

    public function currency()
    {
        return $this->belongsTo('App\Models\Currency');
    }

    public function industry()
    {
        return $this->belongsTo('App\Models\Industry');
    }

    public function default_tax_rate()
    {
        return $this->belongsTo('App\Models\TaxRate');
    }

    public function isGatewayConfigured($gatewayId = 0)
    {
        $this->load('account_gateways');

        if ($gatewayId) {
            return $this->getGatewayConfig($gatewayId) != false;
        } else {
            return count($this->account_gateways) > 0;
        }
    }

    public function isEnglish()
    {
        return !$this->language_id || $this->language_id == DEFAULT_LANGUAGE;
    }

    public function hasInvoicePrefix()
    {
        if ( ! $this->invoice_number_prefix && ! $this->quote_number_prefix) {
            return false;
        }

        return $this->invoice_number_prefix != $this->quote_number_prefix;
    }

    public function getDisplayName()
    {
        if ($this->name) {
            return $this->name;
        }

        $this->load('users');
        $user = $this->users()->first();

        return $user->getDisplayName();
    }

    public function getCityState()
    {
        $swap = $this->country && $this->country->swap_postal_code;
        return Utils::cityStateZip($this->city, $this->state, $this->postal_code, $swap);
    }

    public function getMomentDateTimeFormat()
    {
        $format = $this->datetime_format ? $this->datetime_format->format_moment : DEFAULT_DATETIME_MOMENT_FORMAT;

        if ($this->military_time) {
            $format = str_replace('h:mm:ss a', 'H:mm:ss', $format);
        }

        return $format;
    }

    public function getTimezone()
    {
        if ($this->timezone) {
            return $this->timezone->name;
        } else {
            return 'US/Eastern';
        }
    }

    public function getDateTime($date = 'now')
    {
        return new \DateTime($date, new \DateTimeZone($this->getTimezone()));
    }

    public function getCustomDateFormat()
    {
        return $this->date_format ? $this->date_format->format : DEFAULT_DATE_FORMAT;
    }

    public function formatDate($date)
    {
        if (!$date) {
            return null;
        }

        return $date->format($this->getCustomDateFormat());
    }

    public function getGatewayByType($type = PAYMENT_TYPE_ANY)
    {
        foreach ($this->account_gateways as $gateway) {
            if (!$type || $type == PAYMENT_TYPE_ANY) {
                return $gateway;
            } elseif ($gateway->isPaymentType($type)) {
                return $gateway;
            }
        }
        
        return false;
    }

    public function getGatewayConfig($gatewayId)
    {
        foreach ($this->account_gateways as $gateway) {
            if ($gateway->gateway_id == $gatewayId) {
                return $gateway;
            }
        }

        return false;
    }

    public function hasLogo()
    {
        return file_exists($this->getLogoPath());
    }

    public function getLogoPath()
    {
        $fileName = 'logo/' . $this->account_key;

        return file_exists($fileName.'.png') ? $fileName.'.png' : $fileName.'.jpg';
    }

    public function getLogoURL()
    {
        return SITE_URL . '/' . $this->getLogoPath();
    }

    public function getToken($name)
    {
        foreach ($this->account_tokens as $token) {
            if ($token->name === $name) {
                return $token->token;
            }
        }

        return null;
    }

    public function getLogoWidth()
    {
        $path = $this->getLogoPath();
        if (!file_exists($path)) {
            return 0;
        }
        list($width, $height) = getimagesize($path);

        return $width;
    }

    public function getLogoHeight()
    {
        $path = $this->getLogoPath();
        if (!file_exists($path)) {
            return 0;
        }
        list($width, $height) = getimagesize($path);

        return $height;
    }

    public function createInvoice($entityType, $clientId = null)
    {
        $invoice = Invoice::createNew();

        $invoice->invoice_date = Utils::today();
        $invoice->start_date = Utils::today();
        $invoice->invoice_design_id = $this->invoice_design_id;
        $invoice->client_id = $clientId;
           
        if ($entityType === ENTITY_RECURRING_INVOICE) {
            $invoice->invoice_number = microtime(true);
            $invoice->is_recurring = true;
        } else {
            if ($entityType == ENTITY_QUOTE) {
                $invoice->is_quote = true;
            }

            if ($this->hasClientNumberPattern($invoice) && !$clientId) {
                // do nothing, we don't yet know the value
            } else {
                $invoice->invoice_number = $this->getNextInvoiceNumber($invoice);
            }
        }
        
        if (!$clientId) {
            $invoice->client = Client::createNew();
            $invoice->client->public_id = 0;
        }

        return $invoice;
    }

    public function hasNumberPattern($isQuote)
    {
        return $isQuote ? ($this->quote_number_pattern ? true : false) : ($this->invoice_number_pattern ? true : false);
    }

    public function hasClientNumberPattern($invoice)
    {
        $pattern = $invoice->is_quote ? $this->quote_number_pattern : $this->invoice_number_pattern;
        
        return strstr($pattern, '$custom');
    }

    public function getNumberPattern($invoice)
    {
        $pattern = $invoice->is_quote ? $this->quote_number_pattern : $this->invoice_number_pattern;

        if (!$pattern) {
            return false;
        }

        $search = ['{$year}'];
        $replace = [date('Y')];

        $search[] = '{$counter}';
        $replace[] = str_pad($this->getCounter($invoice->is_quote), 4, '0', STR_PAD_LEFT);

        if (strstr($pattern, '{$userId}')) {
            $search[] = '{$userId}';
            $replace[] = str_pad($invoice->user->public_id, 2, '0', STR_PAD_LEFT);
        }

        $matches = false;
        preg_match('/{\$date:(.*?)}/', $pattern, $matches);
        if (count($matches) > 1) {
            $format = $matches[1];
            $search[] = $matches[0];
            $replace[] = str_replace($format, date($format), $matches[1]);
        }

        $pattern = str_replace($search, $replace, $pattern);

        if ($invoice->client_id) {
            $pattern = $this->getClientInvoiceNumber($pattern, $invoice);
        }

        return $pattern;
    }

    private function getClientInvoiceNumber($pattern, $invoice)
    {
        if (!$invoice->client) {
            return $pattern;
        }

        $search = [
            '{$custom1}',
            '{$custom2}',
        ];

        $replace = [
            $invoice->client->custom_value1,
            $invoice->client->custom_value2,
        ];

        return str_replace($search, $replace, $pattern);
    }

    public function getCounter($isQuote)
    {
        return $isQuote && !$this->share_counter ? $this->quote_number_counter : $this->invoice_number_counter;
    }

    public function getNextInvoiceNumber($invoice)
    {
        if ($this->hasNumberPattern($invoice->is_quote)) {
            return $this->getNumberPattern($invoice);
        }

        $counter = $this->getCounter($invoice->is_quote);
        $prefix = $invoice->is_quote ? $this->quote_number_prefix : $this->invoice_number_prefix;
        $counterOffset = 0;

        // confirm the invoice number isn't already taken 
        do {
            $number = $prefix.str_pad($counter, 4, '0', STR_PAD_LEFT);
            $check = Invoice::scope(false, $this->id)->whereInvoiceNumber($number)->withTrashed()->first();
            $counter++;
            $counterOffset++;
        } while ($check);

        // update the invoice counter to be caught up
        if ($counterOffset > 1) {
            if ($invoice->is_quote && !$this->share_counter) {
                $this->quote_number_counter += $counterOffset - 1;
            } else {
                $this->invoice_number_counter += $counterOffset - 1;
            }

            $this->save();
        }

        return $number;
    }

    public function incrementCounter($invoice)
    {
        if ($invoice->is_quote && !$this->share_counter) {
            $this->quote_number_counter += 1;
        } else {
            $this->invoice_number_counter += 1;
        }
        
        $this->save();
    }

    public function loadLocalizationSettings($client = false)
    {
        $this->load('timezone', 'date_format', 'datetime_format', 'language');

        $timezone = $this->timezone ? $this->timezone->name : DEFAULT_TIMEZONE;
        Session::put(SESSION_TIMEZONE, $timezone);

        Session::put(SESSION_DATE_FORMAT, $this->date_format ? $this->date_format->format : DEFAULT_DATE_FORMAT);
        Session::put(SESSION_DATE_PICKER_FORMAT, $this->date_format ? $this->date_format->picker_format : DEFAULT_DATE_PICKER_FORMAT);

        $currencyId = ($client && $client->currency_id) ? $client->currency_id : $this->currency_id ?: DEFAULT_CURRENCY; 
        $locale = ($client && $client->language_id) ? $client->language->locale : ($this->language_id ? $this->Language->locale : DEFAULT_LOCALE); 

        Session::put(SESSION_CURRENCY, $currencyId);
        Session::put(SESSION_LOCALE, $locale);

        App::setLocale($locale);

        $format = $this->datetime_format ? $this->datetime_format->format : DEFAULT_DATETIME_FORMAT;
        if ($this->military_time) {
            $format = str_replace('g:i a', 'H:i', $format);
        }
        Session::put(SESSION_DATETIME_FORMAT, $format);
    }

    public function getInvoiceLabels()
    {
        $data = [];
        $custom = (array) json_decode($this->invoice_labels);

        $fields = [
            'invoice',
            'invoice_date',
            'due_date',
            'invoice_number',
            'po_number',
            'discount',
            'taxes',
            'tax',
            'item',
            'description',
            'unit_cost',
            'quantity',
            'line_total',
            'subtotal',
            'paid_to_date',
            'balance_due',
            'amount_due',
            'terms',
            'your_invoice',
            'quote',
            'your_quote',
            'quote_date',
            'quote_number',
            'total',
            'invoice_issued_to',
            'date',
            'rate',
            'hours',
            'balance',
            'from',
            'to',
            'invoice_to',
            'details',
            'invoice_no',
            'valid_until',
        ];

        foreach ($fields as $field) {
            if (isset($custom[$field]) && $custom[$field]) {
                $data[$field] = $custom[$field];
            } else {
                $data[$field] = $this->isEnglish() ? uctrans("texts.$field") : trans("texts.$field");
            }
        }

        foreach (['item', 'quantity', 'unit_cost'] as $field) {
            $data["{$field}_orig"] = $data[$field];
        }

        return $data;
    }

    public function isNinjaAccount()
    {
        return $this->account_key === NINJA_ACCOUNT_KEY;
    }

    public function isPro()
    {
        if (!Utils::isNinjaProd()) {
            return true;
        }

        if ($this->isNinjaAccount()) {
            return true;
        }

        $datePaid = $this->pro_plan_paid;

        if ($datePaid == NINJA_DATE) {
            return true;
        }

        return Utils::withinPastYear($datePaid);
    }

    public function isWhiteLabel()
    {
        if ($this->isNinjaAccount()) {
            return false;
        }

        if (Utils::isNinjaProd()) {
            return self::isPro() && $this->pro_plan_paid != NINJA_DATE;
        } else {
            return $this->pro_plan_paid == NINJA_DATE;
        }
    }

    public function getSubscription($eventId)
    {
        return Subscription::where('account_id', '=', $this->id)->where('event_id', '=', $eventId)->first();
    }

    public function hideFieldsForViz()
    {
        foreach ($this->clients as $client) {
            $client->setVisible([
                'public_id',
                'name',
                'balance',
                'paid_to_date',
                'invoices',
                'contacts',
            ]);

            foreach ($client->invoices as $invoice) {
                $invoice->setVisible([
                    'public_id',
                    'invoice_number',
                    'amount',
                    'balance',
                    'invoice_status_id',
                    'invoice_items',
                    'created_at',
                    'is_recurring',
                    'is_quote',
                ]);

                foreach ($invoice->invoice_items as $invoiceItem) {
                    $invoiceItem->setVisible([
                        'product_key',
                        'cost',
                        'qty',
                    ]);
                }
            }

            foreach ($client->contacts as $contact) {
                $contact->setVisible([
                    'public_id',
                    'first_name',
                    'last_name',
                    'email', ]);
            }
        }

        return $this;
    }

    public function getDefaultEmailSubject($entityType)
    {
        if (strpos($entityType, 'reminder') !== false) {
            $entityType = 'reminder';
        }

        return trans("texts.{$entityType}_subject", ['invoice' => '$invoice', 'account' => '$account']);
    }

    public function getEmailSubject($entityType)
    {
        if ($this->isPro()) {
            $field = "email_subject_{$entityType}";
            $value = $this->$field;

            if ($value) {
                return $value;
            }
        }

        return $this->getDefaultEmailSubject($entityType);
    }

    public function getDefaultEmailTemplate($entityType, $message = false)
    {
        if (strpos($entityType, 'reminder') !== false) {
            $entityType = ENTITY_INVOICE;
        }

        $template = "<div>\$client,</div><br/>" .
                    "<div>" . trans("texts.{$entityType}_message", ['amount' => '$amount']) . "</div><br/>" .
                    "<div><a href=\"\$link\">\$link</a></div><br/>";

        if ($message) {
            $template .= "$message<p/>\r\n\r\n";
        }

        return $template . "\$footer";
    }

    public function getEmailTemplate($entityType, $message = false)
    {
        if ($this->isPro()) {
            $field = "email_template_{$entityType}";
            $template = $this->$field;

            if ($template) {
                return $template;
            }
        }
        
        return $this->getDefaultEmailTemplate($entityType, $message);
    }

    public function getEmailFooter()
    {
        if ($this->email_footer) {
            // Add line breaks if HTML isn't already being used
            return strip_tags($this->email_footer) == $this->email_footer ? nl2br($this->email_footer) : $this->email_footer;
        } else {
            return "<p>" . trans('texts.email_signature') . "\n<br>\$account</p>";
        }
    }

    public function showTokenCheckbox()
    {
        if (!$this->isGatewayConfigured(GATEWAY_STRIPE)) {
            return false;
        }

        return $this->token_billing_type_id == TOKEN_BILLING_OPT_IN
                || $this->token_billing_type_id == TOKEN_BILLING_OPT_OUT;
    }

    public function selectTokenCheckbox()
    {
        return $this->token_billing_type_id == TOKEN_BILLING_OPT_OUT;
    }

    public function getSiteUrl()
    {
        $url = SITE_URL;
        $iframe_url = $this->iframe_url;
                
        if ($iframe_url) {
            return "{$iframe_url}/?";
        } else if ($this->subdomain) {
            $url = Utils::replaceSubdomain($url, $this->subdomain);
        }

        return $url;
    }

    public function checkSubdomain($host)
    {
        if (!$this->subdomain) {
            return true;
        }

        $server = explode('.', $host);
        $subdomain = $server[0];

        if (!in_array($subdomain, ['app', 'www']) && $subdomain != $this->subdomain) {
            return false;
        }

        return true;
    }

    public function showCustomField($field, $entity)
    {
        if ($this->isPro()) {
            return $this->$field ? true : false;
        }

        if (!$entity) {
            return false;
        }
        
        // convert (for example) 'custom_invoice_label1' to 'invoice.custom_value1'
        $field = str_replace(['invoice_', 'label'], ['', 'value'], $field);
        
        return Utils::isEmpty($entity->$field) ? false : true;
    }

    public function attatchPDF()
    {
        return $this->isPro() && $this->pdf_email_attachment;
    }
}

Account::updated(function ($account) {
    Event::fire(new UserSettingsChanged());
});
