<?php namespace App\Models;

use Utils;
use DateTime;
use Illuminate\Database\Eloquent\SoftDeletes;
use Laracasts\Presenter\PresentableTrait;
use App\Models\BalanceAffecting;
use App\Models\Client;
use App\Events\QuoteWasCreated;
use App\Events\QuoteWasUpdated;
use App\Events\InvoiceWasCreated;
use App\Events\InvoiceWasUpdated;
use App\Events\InvoiceInvitationWasEmailed;
use App\Events\QuoteInvitationWasEmailed;

class Invoice extends EntityModel implements BalanceAffecting
{
    use PresentableTrait;
    use OwnedByClientTrait;
    use SoftDeletes {
        SoftDeletes::trashed as parentTrashed;
    }

    protected $presenter = 'App\Ninja\Presenters\InvoicePresenter';
    protected $dates = ['deleted_at'];

    protected $casts = [
        'is_recurring' => 'boolean',
        'has_tasks' => 'boolean',
        'auto_bill' => 'boolean',
    ];

    // used for custom invoice numbers
    public static $patternFields = [
        'counter',
        'custom1',
        'custom2',
        'userId',
        'year',
        'date:',
    ];

    public static $fieldInvoiceNumber = 'invoice_number';
    public static $fieldInvoiceDate = 'invoice_date';
    public static $fieldDueDate = 'due_date';
    public static $fieldAmount = 'amount';
    public static $fieldPaid = 'paid';
    public static $fieldNotes = 'notes';
    public static $fieldTerms = 'terms';

    public static function getImportColumns()
    {
        return [
            Client::$fieldName,
            Invoice::$fieldInvoiceNumber,
            Invoice::$fieldInvoiceDate,
            Invoice::$fieldDueDate,
            Invoice::$fieldAmount,
            Invoice::$fieldPaid,
            Invoice::$fieldNotes,
            Invoice::$fieldTerms,
        ];
    }

    public static function getImportMap()
    {
        return [
            'number^po' => 'invoice_number',
            'amount' => 'amount',
            'organization' => 'name',
            'paid^date' => 'paid',
            'invoice_date|create_date' => 'invoice_date',
            'terms' => 'terms',
            'notes' => 'notes',
        ];
    }
    public function getRoute()
    {
        $entityType = $this->getEntityType();
        return "/{$entityType}s/{$this->public_id}/edit";
    }

    public function getDisplayName()
    {
        return $this->is_recurring ? trans('texts.recurring') : $this->invoice_number; 
    }

    public function affectsBalance()
    {
        return !$this->is_quote && !$this->is_recurring;
    }

    public function getAdjustment()
    {
        if (!$this->affectsBalance()) {
            return 0;
        }

        return $this->getRawAdjustment();
    }

    private function getRawAdjustment()
    {
        return floatval($this->amount) - floatval($this->getOriginal('amount'));
    }

    public function isChanged()
    {
        if ($this->getRawAdjustment() != 0) {
            return true;
        }

        foreach ([
            'invoice_number',
            'po_number',
            'invoice_date',
            'due_date',
            'terms',
            'public_notes',
            'invoice_footer',
            'partial',
        ] as $field) {
            if ($this->$field != $this->getOriginal($field)) {
                return true;
            }
        }

        return false;
    }

    public function getAmountPaid()
    {
        if ($this->is_quote || $this->is_recurring) {
            return 0;
        }

        return ($this->amount - $this->balance);
    }
    
    public function trashed()
    {
        if ($this->client && $this->client->trashed()) {
            return true;
        }

        return self::parentTrashed();
    }

    public function account()
    {
        return $this->belongsTo('App\Models\Account');
    }

    public function user()
    {
        return $this->belongsTo('App\Models\User')->withTrashed();
    }

    public function client()
    {
        return $this->belongsTo('App\Models\Client')->withTrashed();
    }

    public function invoice_items()
    {
        return $this->hasMany('App\Models\InvoiceItem')->orderBy('id');
    }

    public function invoice_status()
    {
        return $this->belongsTo('App\Models\InvoiceStatus');
    }

    public function invoice_design()
    {
        return $this->belongsTo('App\Models\InvoiceDesign');
    }

    public function recurring_invoice()
    {
        return $this->belongsTo('App\Models\Invoice');
    }

    public function recurring_invoices()
    {
        return $this->hasMany('App\Models\Invoice', 'recurring_invoice_id');
    }

    public function invitations()
    {
        return $this->hasMany('App\Models\Invitation')->orderBy('invitations.contact_id');
    }

    public function markInvitationsSent($notify = false)
    {
        foreach ($this->invitations as $invitation) {
            $this->markInvitationSent($invitation, false, $notify);
        }
    }

    public function markInvitationSent($invitation, $messageId = false, $notify = true)
    {
        if (!$this->isSent()) {
            $this->invoice_status_id = INVOICE_STATUS_SENT;
            $this->save();
        }

        $invitation->markSent($messageId);

        // if the user marks it as sent rather than acually sending it 
        // then we won't track it in the activity log
        if (!$notify) {
            return;
        }

        if ($this->is_quote) {
            event(new QuoteInvitationWasEmailed($invitation));
        } else {
            event(new InvoiceInvitationWasEmailed($invitation));
        }
    }

    public function markViewed()
    {
        if (!$this->isViewed()) {
            $this->invoice_status_id = INVOICE_STATUS_VIEWED;
            $this->save();
        }
    }

    public function updatePaidStatus($save = true)
    {
        $statusId = false;
        if ($this->amount > 0 && $this->balance == 0) {
            $statusId = INVOICE_STATUS_PAID;
        } elseif ($this->balance > 0 && $this->balance < $this->amount) {
            $statusId = INVOICE_STATUS_PARTIAL;
        } elseif ($this->isPartial() && $this->balance > 0) {
            $statusId = ($this->balance == $this->amount ? INVOICE_STATUS_SENT : INVOICE_STATUS_PARTIAL);
        }

        if ($statusId && $statusId != $this->invoice_status_id) {
            $this->invoice_status_id = $statusId;
            if ($save) {
                $this->save();
            }
        }
    }

    public function updateBalances($balanceAdjustment, $partial = 0)
    {
        if ($this->is_deleted) {
            return;
        }

        $this->balance = $this->balance + $balanceAdjustment;

        if ($this->partial > 0) {
            $this->partial = $partial;
        }

        $this->save();
    }

    public function getName()
    {
        return $this->is_recurring ? trans('texts.recurring') : $this->invoice_number;
    }

    public function getFileName()
    {
        $entityType = $this->getEntityType();
        return trans("texts.$entityType") . '_' . $this->invoice_number . '.pdf';
    }

    public function getPDFPath()
    {
        return storage_path() . '/pdfcache/cache-' . $this->id . '.pdf';
    }

    public static function calcLink($invoice)
    {
        return link_to('invoices/' . $invoice->public_id, $invoice->invoice_number);
    }

    public function getLink()
    {
        return self::calcLink($this);
    }

    public function getEntityType()
    {
        return $this->is_quote ? ENTITY_QUOTE : ENTITY_INVOICE;
    }

    public function isSent()
    {
        return $this->invoice_status_id >= INVOICE_STATUS_SENT;
    }

    public function isViewed()
    {
        return $this->invoice_status_id >= INVOICE_STATUS_VIEWED;
    }

    public function isPartial()
    {
        return $this->invoice_status_id >= INVOICE_STATUS_PARTIAL;
    }

    public function isPaid()
    {
        return $this->invoice_status_id >= INVOICE_STATUS_PAID;
    }

    public function isOverdue()
    {
        if ( ! $this->due_date) {
            return false;
        }

        return time() > strtotime($this->due_date);
    }

    public function getRequestedAmount()
    {
        return $this->partial > 0 ? $this->partial : $this->balance;
    }

    public function hidePrivateFields()
    {
        $this->setVisible([
            'invoice_number',
            'discount',
            'is_amount_discount',
            'po_number',
            'invoice_date',
            'due_date',
            'terms',
            'invoice_footer',
            'public_notes',
            'amount',
            'balance',
            'invoice_items',
            'client',
            'tax_name',
            'tax_rate',
            'account',
            'invoice_design',
            'invoice_design_id',
            'is_pro',
            'is_quote',
            'custom_value1',
            'custom_value2',
            'custom_taxes1',
            'custom_taxes2',
            'partial',
            'has_tasks',
            'custom_text_value1',
            'custom_text_value2',
        ]);

        $this->client->setVisible([
            'name',
            'id_number',
            'vat_number',
            'address1',
            'address2',
            'city',
            'state',
            'postal_code',
            'work_phone',
            'payment_terms',
            'contacts',
            'country',
            'currency_id',
            'custom_value1',
            'custom_value2',
        ]);

        $this->account->setVisible([
            'name',
            'id_number',
            'vat_number',
            'address1',
            'address2',
            'city',
            'state',
            'postal_code',
            'work_phone',
            'work_email',
            'country',
            'currency_id',
            'custom_label1',
            'custom_value1',
            'custom_label2',
            'custom_value2',
            'custom_client_label1',
            'custom_client_label2',
            'primary_color',
            'secondary_color',
            'hide_quantity',
            'hide_paid_to_date',
            'custom_invoice_label1',
            'custom_invoice_label2',
            'pdf_email_attachment',
            'show_item_taxes',
            'custom_invoice_text_label1',
            'custom_invoice_text_label2',
        ]);

        foreach ($this->invoice_items as $invoiceItem) {
            $invoiceItem->setVisible([
                'product_key',
                'notes',
                'cost',
                'qty',
                'tax_name',
                'tax_rate',
            ]);
        }

        foreach ($this->client->contacts as $contact) {
            $contact->setVisible([
                'first_name',
                'last_name',
                'email',
                'phone',
            ]);
        }

        return $this;
    }

    public function getSchedule()
    {
        if (!$this->start_date || !$this->is_recurring || !$this->frequency_id) {
            return false;
        }

        $startDate = $this->getOriginal('last_sent_date') ?: $this->getOriginal('start_date');
        $startDate .= ' ' . $this->account->recurring_hour . ':00:00';
        $startDate = $this->account->getDateTime($startDate);
        $endDate = $this->end_date ? $this->account->getDateTime($this->getOriginal('end_date')) : null;
        $timezone = $this->account->getTimezone();

        $rule = $this->getRecurrenceRule();
        $rule = new \Recurr\Rule("{$rule}", $startDate, $endDate, $timezone);

        // Fix for months with less than 31 days
        $transformerConfig = new \Recurr\Transformer\ArrayTransformerConfig();
        $transformerConfig->enableLastDayOfMonthFix();
        
        $transformer = new \Recurr\Transformer\ArrayTransformer();
        $transformer->setConfig($transformerConfig);
        $dates = $transformer->transform($rule);

        if (count($dates) < 2) {
            return false;
        }

        return $dates;
    }

    public function getNextSendDate()
    {
        if ($this->start_date && !$this->last_sent_date) {
            $startDate = $this->getOriginal('start_date') . ' ' . $this->account->recurring_hour . ':00:00';
            return $this->account->getDateTime($startDate);
        }

        if (!$schedule = $this->getSchedule()) {
            return null;
        }

        if (count($schedule) < 2) {
            return null;
        }
        
        return $schedule[1]->getStart();
    }

    public function getPrettySchedule($min = 1, $max = 10)
    {
        if (!$schedule = $this->getSchedule($max)) {
            return null;
        }

        $dates = [];

        for ($i=$min; $i<min($max, count($schedule)); $i++) {
            $date = $schedule[$i];
            $date = $this->account->formatDate($date->getStart());
            $dates[] = $date;
        }

        return implode('<br/>', $dates);
    }

    private function getRecurrenceRule()
    {
        $rule = '';

        switch ($this->frequency_id) {
            case FREQUENCY_WEEKLY:
                $rule = 'FREQ=WEEKLY;';
                break;
            case FREQUENCY_TWO_WEEKS:
                $rule = 'FREQ=WEEKLY;INTERVAL=2;';
                break;
            case FREQUENCY_FOUR_WEEKS:
                $rule = 'FREQ=WEEKLY;INTERVAL=4;';
                break;
            case FREQUENCY_MONTHLY:
                $rule = 'FREQ=MONTHLY;';
                break;
            case FREQUENCY_THREE_MONTHS:
                $rule = 'FREQ=MONTHLY;INTERVAL=3;';
                break;
            case FREQUENCY_SIX_MONTHS:
                $rule = 'FREQ=MONTHLY;INTERVAL=6;';
                break;
            case FREQUENCY_ANNUALLY:
                $rule = 'FREQ=YEARLY;';
                break;
        }

        if ($this->end_date) {
            $rule .= 'UNTIL=' . $this->getOriginal('end_date');
        }

        return $rule;
    }

    /*
    public function shouldSendToday()
    {
        if (!$nextSendDate = $this->getNextSendDate()) {
            return false;
        }
        
        return $this->account->getDateTime() >= $nextSendDate;
    }
    */

    public function shouldSendToday()
    {
        if (!$this->start_date || strtotime($this->start_date) > strtotime('now')) {
            return false;
        }

        if ($this->end_date && strtotime($this->end_date) < strtotime('now')) {
            return false;
        }

        $dayOfWeekToday = date('w');
        $dayOfWeekStart = date('w', strtotime($this->start_date));

        $dayOfMonthToday = date('j');
        $dayOfMonthStart = date('j', strtotime($this->start_date));

        if (!$this->last_sent_date) {
            return true;
        } else {
            $date1 = new DateTime($this->last_sent_date);
            $date2 = new DateTime();
            $diff = $date2->diff($date1);
            $daysSinceLastSent = $diff->format("%a");
            $monthsSinceLastSent = ($diff->format('%y') * 12) + $diff->format('%m');

            if ($daysSinceLastSent == 0) {
                return false;
            }
        }

        switch ($this->frequency_id) {
            case FREQUENCY_WEEKLY:
                return $daysSinceLastSent >= 7;
            case FREQUENCY_TWO_WEEKS:
                return $daysSinceLastSent >= 14;
            case FREQUENCY_FOUR_WEEKS:
                return $daysSinceLastSent >= 28;
            case FREQUENCY_MONTHLY:
                return $monthsSinceLastSent >= 1;
            case FREQUENCY_THREE_MONTHS:
                return $monthsSinceLastSent >= 3;
            case FREQUENCY_SIX_MONTHS:
                return $monthsSinceLastSent >= 6;
            case FREQUENCY_ANNUALLY:
                return $monthsSinceLastSent >= 12;
            default:
                return false;
        }

        return false;
    }

    public function getReminder()
    {
        for ($i=1; $i<=3; $i++) {
            $field = "enable_reminder{$i}";
            if (!$this->account->$field) {
                continue;
            }
            $field = "num_days_reminder{$i}";
            $date = date('Y-m-d', strtotime("- {$this->account->$field} days"));

            if ($this->due_date == $date) {
                return "reminder{$i}";
            }
        }

        return false;
    }

    public function getPDFString()
    {
        if (!env('PHANTOMJS_CLOUD_KEY')) {
            return false;
        }

        $invitation = $this->invitations[0];
        $link = $invitation->getLink();
        $curl = curl_init();

        $jsonEncodedData = json_encode([
            'url' => "{$link}?phantomjs=true",
            'renderType' => 'html',
            'outputAsJson' => false,
            'renderSettings' => [
                'passThroughHeaders' => true,
            ],
            // 'delayTime' => 1000,
        ]);

        $opts = [
            CURLOPT_URL => PHANTOMJS_CLOUD . env('PHANTOMJS_CLOUD_KEY') . '/',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POST => 1,
            CURLOPT_POSTFIELDS => $jsonEncodedData,
            CURLOPT_HTTPHEADER  => [
                'Content-Type: application/json',
                'Content-Length: '.strlen($jsonEncodedData)
            ],
        ];

        curl_setopt_array($curl, $opts);
        $encodedString = strip_tags(curl_exec($curl));
        curl_close($curl);

        return Utils::decodePDF($encodedString);
    }
}

Invoice::creating(function ($invoice) {
    if (!$invoice->is_recurring) {
        $invoice->account->incrementCounter($invoice);
    }
});

Invoice::created(function ($invoice) {
    if ($invoice->is_quote) {
        event(new QuoteWasCreated($invoice));
    } else {
        event(new InvoiceWasCreated($invoice));
    }
});

Invoice::updating(function ($invoice) {
    if ($invoice->is_quote) {
        event(new QuoteWasUpdated($invoice));
    } else {
        event(new InvoiceWasUpdated($invoice));
    }
});
