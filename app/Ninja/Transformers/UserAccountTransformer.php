<?php

namespace App\Ninja\Transformers;

use App\Models\Account;
use App\Models\User;

class UserAccountTransformer extends EntityTransformer
{
    protected $defaultIncludes = [
        'user',
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

    public function transform(User $user)
    {
        $account = $user->account;

        return [
            'account_key' => $account->account_key,
            'name' => $account->present()->name,
            'token' => $account->getToken($user->id, $this->tokenName),
            'default_url' => SITE_URL,
            'plan' => $account->company->plan,
            'logo' => $account->logo,
            'logo_url' => $account->getLogoURL(),
            'currency_id' => (int) $account->currency_id,
            'timezone_id' => (int) $account->timezone_id,
            'date_format_id' => (int) $account->date_format_id,
            'datetime_format_id' => (int) $account->datetime_format_id,
            'invoice_terms' => $account->invoice_terms,
            'invoice_taxes' => (bool) $account->invoice_taxes,
            'invoice_item_taxes' => (bool) $account->invoice_item_taxes,
            'invoice_design_id' => (int) $account->invoice_design_id,
            'quote_design_id' => (int) $account->quote_design_id,
            'language_id' => (int) $account->language_id,
            'invoice_footer' => $account->invoice_footer,
            'invoice_labels' => $account->invoice_labels,
            'show_item_taxes' => (bool) $account->show_item_taxes,
            'military_time' => (bool) $account->military_time,
            'tax_name1' => $account->tax_name1 ?: '',
            'tax_rate1' => (float) $account->tax_rate1,
            'tax_name2' => $account->tax_name2 ?: '',
            'tax_rate2' => (float) $account->tax_rate2,
            'quote_terms' => $account->quote_terms,
            'show_currency_code' => (bool) $account->show_currency_code,
            'enable_second_tax_rate' => (bool) $account->enable_second_tax_rate,
            'start_of_week' => $account->start_of_week,
            'financial_year_start' => $account->financial_year_start,
            'enabled_modules' => (int) $account->enabled_modules,
            'payment_terms' => (int) $account->payment_terms,
            'payment_type_id' => (int) $account->payment_type_id,
            'task_rate' => (float) $account->task_rate,
            'inclusive_taxes' => (bool) $account->inclusive_taxes,
            'convert_products' => (bool) $account->convert_products,
            'custom_invoice_taxes1' => $account->custom_invoice_taxes1,
            'custom_invoice_taxes2' => $account->custom_invoice_taxes1,
            'custom_fields' => $account->custom_fields,
        ];
    }
}
