<?php

namespace App\Ninja\Transformers;

use App\Models\Account;
use App\Models\User;

class UserAccountTransformer extends EntityTransformer
{
	   /**
     * @SWG\Property(property="account_key", type="string", example="123456")
     * @SWG\Property(property="name", type="string", example="John Doe")
     * @SWG\Property(property="token", type="string", example="Token")
     * @SWG\Property(property="default_url", type="string", example="http://www.example.com")
     * @SWG\Property(property="plan", type="string", example="Plan")
     * @SWG\Property(property="logo", type="string", example="Logo")
     * @SWG\Property(property="logo_url", type="string", example="http://www.example.com/logo.png")
     * @SWG\Property(property="currency_id", type="integer", example=1)
     * @SWG\Property(property="timezone_id", type="integer", example=1)
     * @SWG\Property(property="date_format_id", type="integer", example=1)
     * @SWG\Property(property="datetime_format_id", type="integer", example=1)
     * @SWG\Property(property="invoice_terms", type="string", example="Terms")
     * @SWG\Property(property="invoice_taxes", type="boolean", example=false)
     * @SWG\Property(property="invoice_item_taxes", type="boolean", example=false)
     * @SWG\Property(property="invoice_design_id", type="integer", example=1)
     * @SWG\Property(property="quote_design_id", type="integer", example=1)
     * @SWG\Property(property="language_id", type="integer", example=1)
     * @SWG\Property(property="country_id", type="integer", example=1)
     * @SWG\Property(property="invoice_footer", type="string", example="Footer")
     * @SWG\Property(property="invoice_labels", type="string", example="Labels")
     * @SWG\Property(property="show_item_taxes", type="boolean", example=false)
     * @SWG\Property(property="military_time", type="boolean", example=false)
     * @SWG\Property(property="tax_name1", type="string", example="VAT")
     * @SWG\Property(property="tax_name2", type="string", example="Upkeep")
     * @SWG\Property(property="tax_rate1", type="number", format="float", example="17.5")
     * @SWG\Property(property="tax_rate2", type="number", format="float", example="30.0")
     * @SWG\Property(property="quote_terms", type="string", example="Labels")
     * @SWG\Property(property="show_currency_code", type="boolean", example=false)
     * @SWG\Property(property="enable_second_tax_rate", type="boolean", example=false)
     * @SWG\Property(property="start_of_week", type="string", example="Monday")
     * @SWG\Property(property="financial_year_start", type="string", example="January")
     * @SWG\Property(property="enabled_modules", type="integer", example=1)
     * @SWG\Property(property="payment_terms", type="integer", example=1)
     * @SWG\Property(property="payment_type_id", type="integer", example=1)
     * @SWG\Property(property="task_rate", type="number", format="float", example="17.5")
     * @SWG\Property(property="inclusive_taxes", type="boolean", example=false)
     * @SWG\Property(property="convert_products", type="boolean", example=false)
     * @SWG\Property(property="custom_invoice_taxes1", type="string", example="Value")
     * @SWG\Property(property="custom_invoice_taxes2", type="string", example="Value")
     * @SWG\Property(property="custom_fields", type="string", example="Value")
     */
    protected $defaultIncludes = [
        'user',
    ];

    /**
     * @var array
     */
    protected $availableIncludes = [
		'users',
        'tax_rates',
        'expense_categories',
        'account_email_settings',
        'custom_payment_terms',
    ];

    protected $tokenName;

    public function __construct(Account $account, $serializer, $tokenName)
    {
        parent::__construct($account, $serializer);

        $this->tokenName = $tokenName;
    }

    public function includeUser(User $user)
    {
        $transformer = new UserTransformer($this->account, $this->serializer);

        return $this->includeItem($user, $transformer, 'user');
    }

	/**
     * @param Account $account
     *
     * @return \League\Fractal\Resource\Collection
     */
    public function includeCustomPaymentTerms(User $user)
    {
        $transformer = new PaymentTermTransformer($this->account, $this->serializer);

        return $this->includeCollection($this->account->custom_payment_terms, $transformer, 'payment_terms');
    }

	/**
     * @param Account $account
     *
     * @return \League\Fractal\Resource\Collection
     */
    public function includeUsers(User $user)
    {
        $transformer = new UserTransformer($this->account, $this->serializer);

        return $this->includeCollection($this->account->users, $transformer, 'users');
    }

    /**
     * @param Account $account
     *
     * @return \League\Fractal\Resource\Collection
     */
    public function includeAccountEmailSettings(User $user)
    {
        $transformer = new AccountEmailSettingsTransformer($this->account, $this->serializer);

        return $this->includeItem($this->account->account_email_settings, $transformer, 'account_email_settings');
    }

    /**
     * @param Account $account
     *
     * @return \League\Fractal\Resource\Collection
     */
    public function includeExpenseCategories(User $user)
    {
        $transformer = new ExpenseCategoryTransformer($this->account, $this->serializer);

        return $this->includeCollection($this->account->expense_categories, $transformer, 'expense_categories');
    }

    /**
     * @param Account $account
     *
     * @return \League\Fractal\Resource\Collection
     */
    public function includeTaxRates(User $user)
    {
        $transformer = new TaxRateTransformer($this->account, $this->serializer);

        return $this->includeCollection($this->account->tax_rates, $transformer, 'tax_rates');
    }

    public function transform(User $user)
    {
        $account = $user->account;

        return [
            'account_key' => $account->account_key,
            'user_id' => (int) ($user->public_id + 1),
            'name' => $account->present()->name ?: '',
            'token' => $account->getToken($user->id, $this->tokenName),
            'default_url' => SITE_URL,
            'plan' => $account->company->plan ?: '',
            'logo' => $account->logo ?: '',
            'logo_url' => $account->getLogoURL() ?: '',
            'currency_id' => (int) $account->currency_id,
            'timezone_id' => (int) $account->timezone_id,
            'date_format_id' => (int) $account->date_format_id,
            'datetime_format_id' => (int) $account->datetime_format_id,
            'invoice_terms' => $account->invoice_terms ?: '',
            'invoice_taxes' => (bool) $account->invoice_taxes,
            'invoice_item_taxes' => (bool) $account->invoice_item_taxes,
            'invoice_design_id' => (int) $account->invoice_design_id,
            'quote_design_id' => (int) $account->quote_design_id,
            'language_id' => (int) $account->language_id,
            'country_id' => (int) $account->country_id,
            'invoice_footer' => $account->invoice_footer ?: '',
            'invoice_labels' => $account->invoice_labels ?: '',
            'show_item_taxes' => (bool) $account->show_item_taxes,
            'military_time' => (bool) $account->military_time,
            'tax_name1' => $account->tax_name1 ?: '',
            'tax_rate1' => (float) $account->tax_rate1,
            'tax_name2' => $account->tax_name2 ?: '',
            'tax_rate2' => (float) $account->tax_rate2,
            'quote_terms' => $account->quote_terms ?: '',
            'show_currency_code' => (bool) $account->show_currency_code,
            'enable_second_tax_rate' => (bool) $account->enable_second_tax_rate,
            'start_of_week' => (int) $account->start_of_week,
            'financial_year_start' => (int) $account->financial_year_start,
            'enabled_modules' => (int) $account->enabled_modules,
            'payment_terms' => (int) $account->payment_terms,
            'payment_type_id' => (int) $account->payment_type_id,
            'task_rate' => (float) $account->task_rate,
            'inclusive_taxes' => (bool) $account->inclusive_taxes,
            'convert_products' => (bool) $account->convert_products,
            'custom_invoice_taxes1' => (bool) $account->custom_invoice_taxes1,
            'custom_invoice_taxes2' => (bool) $account->custom_invoice_taxes1,
            'custom_fields' => $account->custom_fields ?: '',
            'invoice_fields' => $account->invoice_fields ?: '',
            'custom_messages' => $account->custom_messages,
			'email_footer' => $account->getEmailFooter(),
            'email_subject_invoice' => $account->getEmailSubject(ENTITY_INVOICE),
            'email_subject_quote' => $account->getEmailSubject(ENTITY_QUOTE),
            'email_subject_payment' => $account->getEmailSubject(ENTITY_PAYMENT),
            'email_template_invoice' => $account->getEmailTemplate(ENTITY_INVOICE),
            'email_template_quote' => $account->getEmailTemplate(ENTITY_QUOTE),
            'email_template_payment' => $account->getEmailTemplate(ENTITY_PAYMENT),
            'email_subject_reminder1' => $account->getEmailSubject('reminder1'),
            'email_subject_reminder2' => $account->getEmailSubject('reminder2'),
            'email_subject_reminder3' => $account->getEmailSubject('reminder3'),
            'email_template_reminder1' => $account->getEmailTemplate('reminder1'),
            'email_template_reminder2' => $account->getEmailTemplate('reminder2'),
            'email_template_reminder3' => $account->getEmailTemplate('reminder3'),
        ];
    }
}
