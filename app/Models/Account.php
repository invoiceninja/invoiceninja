<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2021. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://opensource.org/licenses/AAL
 */

namespace App\Models;

use App\Models\Presenters\AccountPresenter;
use App\Utils\Ninja;
use App\Utils\Traits\MakesHash;
use DateTime;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Laracasts\Presenter\PresentableTrait;

class Account extends BaseModel
{
    use PresentableTrait;
    use MakesHash;

    /**
     * @var string
     */
    protected $presenter = AccountPresenter::class;

    /**
     * @var array
     */
    protected $fillable = [
        'plan',
        'plan_term',
        'plan_price',
        'plan_paid',
        'plan_started',
        'plan_expires',
        'utm_source',
        'utm_medium',
        'utm_campaign',
        'utm_term',
        'utm_content',
        'user_agent',
    ];

    /**
     * @var array
     */
    protected $dates = [
        'deleted_at',
        'promo_expires',
        'discount_expires',
    ];

    const PLAN_FREE = 'free';
    const PLAN_PRO = 'pro';
    const PLAN_ENTERPRISE = 'enterprise';
    const PLAN_WHITE_LABEL = 'white_label';
    const PLAN_TERM_MONTHLY = 'month';
    const PLAN_TERM_YEARLY = 'year';

    const FEATURE_TASKS = 'tasks';
    const FEATURE_EXPENSES = 'expenses';
    const FEATURE_QUOTES = 'quotes';
    const FEATURE_CUSTOMIZE_INVOICE_DESIGN = 'custom_designs';
    const FEATURE_DIFFERENT_DESIGNS = 'different_designs';
    const FEATURE_EMAIL_TEMPLATES_REMINDERS = 'template_reminders';
    const FEATURE_INVOICE_SETTINGS = 'invoice_settings';
    const FEATURE_CUSTOM_EMAILS = 'custom_emails';
    const FEATURE_PDF_ATTACHMENT = 'pdf_attachments';
    const FEATURE_MORE_INVOICE_DESIGNS = 'more_invoice_designs';
    const FEATURE_REPORTS = 'reports';
    const FEATURE_BUY_NOW_BUTTONS = 'buy_now_buttons';
    const FEATURE_API = 'api';
    const FEATURE_CLIENT_PORTAL_PASSWORD = 'client_portal_password';
    const FEATURE_CUSTOM_URL = 'custom_url';
    const FEATURE_MORE_CLIENTS = 'more_clients';
    const FEATURE_WHITE_LABEL = 'white_label';
    const FEATURE_REMOVE_CREATED_BY = 'remove_created_by';
    const FEATURE_USERS = 'users'; // Grandfathered for old Pro users
    const FEATURE_DOCUMENTS = 'documents';
    const FEATURE_USER_PERMISSIONS = 'permissions';

    const RESULT_FAILURE = 'failure';
    const RESULT_SUCCESS = 'success';

    public function getEntityType()
    {
        return self::class;
    }

    public function users()
    {
        return $this->hasMany(User::class)->withTrashed();
    }

    public function default_company()
    {
        return $this->hasOne(Company::class, 'id', 'default_company_id');
    }

    /**
     * @return BelongsTo
     */
    public function payment()
    {
        return $this->belongsTo(Payment::class)->withTrashed();
    }

    public function companies()
    {
        return $this->hasMany(Company::class);
    }

    public function company_users()
    {
        return $this->hasMany(CompanyUser::class);
    }

    public function getPlan()
    {
        return $this->plan ?: '';
    }

    public function hasFeature($feature)
    {
        $plan_details = $this->getPlanDetails();
        $self_host = ! Ninja::isNinja();

        switch ($feature) {

            case self::FEATURE_TASKS:
            case self::FEATURE_EXPENSES:
            case self::FEATURE_QUOTES:
                return true;

            case self::FEATURE_CUSTOMIZE_INVOICE_DESIGN:
            case self::FEATURE_DIFFERENT_DESIGNS:
            case self::FEATURE_EMAIL_TEMPLATES_REMINDERS:
            case self::FEATURE_INVOICE_SETTINGS:
            case self::FEATURE_CUSTOM_EMAILS:
            case self::FEATURE_PDF_ATTACHMENT:
            case self::FEATURE_MORE_INVOICE_DESIGNS:
            case self::FEATURE_REPORTS:
            case self::FEATURE_BUY_NOW_BUTTONS:
            case self::FEATURE_API:
            case self::FEATURE_CLIENT_PORTAL_PASSWORD:
            case self::FEATURE_CUSTOM_URL:
                return $self_host || ! empty($plan_details);

            // Pro; No trial allowed, unless they're trialing enterprise with an active pro plan
            case self::FEATURE_MORE_CLIENTS:
                return $self_host || ! empty($plan_details) && (! $plan_details['trial'] || ! empty($this->getPlanDetails(false, false)));

            // White Label
            case self::FEATURE_WHITE_LABEL:
                if (! $self_host && $plan_details && ! $plan_details['expires']) {
                    return false;
                }
                // Fallthrough
                // no break
            case self::FEATURE_REMOVE_CREATED_BY:
                return ! empty($plan_details); // A plan is required even for self-hosted users

            // Enterprise; No Trial allowed; grandfathered for old pro users
            case self::FEATURE_USERS:// Grandfathered for old Pro users
                if ($plan_details && $plan_details['trial']) {
                    // Do they have a non-trial plan?
                    $plan_details = $this->getPlanDetails(false, false);
                }

                return $self_host || ! empty($plan_details) && ($plan_details['plan'] == self::PLAN_ENTERPRISE);

            // Enterprise; No Trial allowed
            case self::FEATURE_DOCUMENTS:
            case self::FEATURE_USER_PERMISSIONS:
                return $self_host || ! empty($plan_details) && $plan_details['plan'] == self::PLAN_ENTERPRISE && ! $plan_details['trial'];

            default:
                return false;
        }
    }

    public function isPaid()
    {
        return Ninja::isNinja() ? ($this->isPaidHostedClient() && ! $this->isTrial()) : $this->hasFeature(self::FEATURE_WHITE_LABEL);
    }

    public function isPaidHostedClient()
    {
        if (! Ninja::isNinja()) {
            return false;
        }

        return $this->plan == 'pro' || $this->plan == 'enterprise';
    }

    public function isFreeHostedClient()
    {
        if (! Ninja::isNinja()) {
            return false;
        }

        return $this->plan == 'free';
    }

    public function isEnterpriseClient()
    {
        if (! Ninja::isNinja()) {
            return false;
        }

        return $this->plan == 'enterprise';
    }

    public function isTrial()
    {
        if (! Ninja::isNinja()) {
            return false;
        }

        $plan_details = $this->getPlanDetails();

        return $plan_details && $plan_details['trial'];
    }

    public function getPlanDetails($include_inactive = false, $include_trial = true)
    {
        $plan = $this->plan;
        $price = $this->plan_price;
        $trial_plan = $this->trial_plan;

        if ((! $plan || $plan == self::PLAN_FREE) && (! $trial_plan || ! $include_trial)) {
            return null;
        }

        $trial_active = false;
        if ($trial_plan && $include_trial) {
            $trial_started = DateTime::createFromFormat('Y-m-d', $this->trial_started);
            $trial_expires = clone $trial_started;
            $trial_expires->modify('+2 weeks');

            if ($trial_expires >= date_create()) {
                $trial_active = true;
            }
        }

        $plan_active = false;
        if ($plan) {
            if ($this->plan_expires == null) {
                $plan_active = true;
                $plan_expires = false;
            } else {
                $plan_expires = DateTime::createFromFormat('Y-m-d', $this->plan_expires);
                if ($plan_expires >= date_create()) {
                    $plan_active = true;
                }
            }
        }

        if (! $include_inactive && ! $plan_active && ! $trial_active) {
            return null;
        }

        // Should we show plan details or trial details?
        if (($plan && ! $trial_plan) || ! $include_trial) {
            $use_plan = true;
        } elseif (! $plan && $trial_plan) {
            $use_plan = false;
        } else {
            // There is both a plan and a trial
            if (! empty($plan_active) && empty($trial_active)) {
                $use_plan = true;
            } elseif (empty($plan_active) && ! empty($trial_active)) {
                $use_plan = false;
            } elseif (! empty($plan_active) && ! empty($trial_active)) {
                // Both are active; use whichever is a better plan
                if ($plan == self::PLAN_ENTERPRISE) {
                    $use_plan = true;
                } elseif ($trial_plan == self::PLAN_ENTERPRISE) {
                    $use_plan = false;
                } else {
                    // They're both the same; show the plan
                    $use_plan = true;
                }
            } else {
                // Neither are active; use whichever expired most recently
                $use_plan = $plan_expires >= $trial_expires;
            }
        }

        if ($use_plan) {
            return [
                'account_id' => $this->id,
                'num_users' => $this->num_users,
                'plan_price' => $price,
                'trial' => false,
                'plan' => $plan,
                'started' => DateTime::createFromFormat('Y-m-d', $this->plan_started),
                'expires' => $plan_expires,
                'paid' => DateTime::createFromFormat('Y-m-d', $this->plan_paid),
                'term' => $this->plan_term,
                'active' => $plan_active,
            ];
        } else {
            return [
                'account_id' => $this->id,
                'num_users' => 1,
                'plan_price' => 0,
                'trial' => true,
                'plan' => $trial_plan,
                'started' => $trial_started,
                'expires' => $trial_expires,
                'active' => $trial_active,
            ];
        }
    }
}
