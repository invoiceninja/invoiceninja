<?php

namespace App\Ninja\Transformers;

use App\Models\AccountEmailSettings;

/**
 * Class AccountTransformer.
 */
class AccountEmailSettingsTransformer extends EntityTransformer
{
    /**
     * @var array
     */
    protected $defaultIncludes = [];

    /**
     * @var array
     */
    protected $availableIncludes = [];

    /**
     * @param Account $settings
     *
     * @throws \Laracasts\Presenter\Exceptions\PresenterException
     *
     * @return array
     */
    public function transform(AccountEmailSettings $settings)
    {
        return [
            'reply_to_email' => $settings->reply_to_email,
            'bcc_email' => $settings->bcc_email,
            'email_subject_invoice' => $settings->email_subject_invoice,
            'email_subject_quote' => $settings->email_subject_quote,
            'email_subject_payment' => $settings->email_subject_payment,
            'email_template_invoice' => $settings->email_template_invoice,
            'email_template_quote' => $settings->email_template_quote,
            'email_template_payment' => $settings->email_template_payment,
            'email_subject_reminder1' => $settings->email_subject_reminder1,
            'email_subject_reminder2' => $settings->email_subject_reminder2,
            'email_subject_reminder3' => $settings->email_subject_reminder3,
            'email_template_reminder1' => $settings->email_template_reminder1,
            'email_template_reminder2' => $settings->email_template_reminder2,
            'email_template_reminder3' => $settings->email_template_reminder3,
        ];
    }
}
