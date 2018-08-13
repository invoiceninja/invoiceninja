<?php

namespace App\Ninja\Transformers;

use App\Models\AccountEmailSettings;

/**
 * Class AccountTransformer.
 */
class AccountEmailSettingsTransformer extends EntityTransformer
{
	  /**
     * @SWG\Property(property="reply_to_email", type="string", example="Reply To Email")
     * @SWG\Property(property="bcc_email", type="string", example="Bcc Email")
     * @SWG\Property(property="email_subject_invoice", type="string", example="Email Subject Invoice")
     * @SWG\Property(property="email_subject_quote", type="string", example="Email Subject Quote")
     * @SWG\Property(property="email_subject_payment", type="string", example="Email subject Payment")
     * @SWG\Property(property="email_template_invoice", type="string", example="Email Template Invoice")
     * @SWG\Property(property="email_template_quote", type="string", example="Email Template Quote")
     * @SWG\Property(property="email_template_payment", type="string", example="Email Template Payment")
     * @SWG\Property(property="email_subject_reminder1", type="string", example="Email Subject Reminder 1")
     * @SWG\Property(property="email_subject_reminder2", type="string", example="Email Subject Reminder 2")
     * @SWG\Property(property="email_subject_reminder3", type="string", example="Email Subject Reminder 3")
     * @SWG\Property(property="email_subject_reminder4", type="string", example="Email Subject Reminder 4")
     * @SWG\Property(property="email_template_reminder1", type="string", example="Email Template Reminder 1")
     * @SWG\Property(property="email_template_reminder2", type="string", example="Email Template Reminder 2")
     * @SWG\Property(property="email_template_reminder3", type="string", example="Email Template Reminder 3")
     * @SWG\Property(property="email_template_reminder4", type="string", example="Email Template Reminder 4")
     * @SWG\Property(property="late_fee1_amount", type="string", example="Late Fee1 Amount")
     * @SWG\Property(property="late_fee1_percent", type="string", example="Late Fee1 Percent")
     * @SWG\Property(property="late_fee2_amount", type="string", example="Late Fee2 Amount")
     * @SWG\Property(property="late_fee2_percent", type="string", example="Late Fee2 Percent")
     * @SWG\Property(property="late_fee3_amount", type="string", example="Late Fee3 Amount")
     * @SWG\Property(property="late_fee3_percent", type="string", example="Late Fee3 Percent")
     * @SWG\Property(property="enable_reminder1", type="string", example="Enable Reminder 1")
     * @SWG\Property(property="enable_reminder2", type="string", example="Enable Reminder 2")
     * @SWG\Property(property="enable_reminder3", type="string", example="Enable reminder 3")
     * @SWG\Property(property="enable_reminder4", type="string", example="Enable Reminder 4")
     * @SWG\Property(property="num_days_reminder1", type="string", example="Num Days reminder 1")
     * @SWG\Property(property="num_days_reminder2", type="string", example="Num Days reminder 2")
     * @SWG\Property(property="num_days_reminder3", type="string", example="Num Days reminder 3")
     * @SWG\Property(property="direction_reminder1", type="integer", example=1)
     * @SWG\Property(property="direction_reminder2", type="integer", example=1)
     * @SWG\Property(property="direction_reminder3", type="integer", example=1)
     * @SWG\Property(property="field_reminder1", type="integer", example=1)
     * @SWG\Property(property="field_reminder2", type="integer", example=1)
     * @SWG\Property(property="field_reminder3", type="integer", example=1)
     * @SWG\Property(property="email_design_id", type="string", example="Email Design ID")
     * @SWG\Property(property="enable_email_markup", type="boolean", example=false)
     * @SWG\Property(property="email_footer", type="string", example="Footer")
     */


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
            'email_subject_reminder4' => $settings->email_subject_reminder4,
            'email_template_reminder1' => $settings->email_template_reminder1,
            'email_template_reminder2' => $settings->email_template_reminder2,
            'email_template_reminder3' => $settings->email_template_reminder3,
            'email_template_reminder4' => $settings->email_template_reminder4,
            'late_fee1_amount' => $settings->late_fee1_amount,
            'late_fee1_percent' => $settings->late_fee1_percent,
            'late_fee2_amount' => $settings->late_fee2_amount,
            'late_fee2_percent' => $settings->late_fee2_percent,
            'late_fee3_amount' => $settings->late_fee3_amount,
            'late_fee3_percent' => $settings->late_fee3_percent,
            'enable_reminder1' => $settings->enable_reminder1,
            'enable_reminder2' => $settings->enable_reminder2,
            'enable_reminder3' => $settings->enable_reminder3,
            'enable_reminder4' => $settings->enable_reminder4,
            'num_days_reminder1' => $settings->num_days_reminder1,
            'num_days_reminder2' => $settings->num_days_reminder2,
            'num_days_reminder3' => $settings->num_days_reminder3,
            'direction_reminder1' => (int) $settings->direction_reminder1,
            'direction_reminder2' => (int) $settings->direction_reminder2,
            'direction_reminder3' => (int) $settings->direction_reminder3,
            'field_reminder1' => (int) $settings->field_reminder1,
            'field_reminder2' => (int) $settings->field_reminder2,
            'field_reminder3' => (int) $settings->field_reminder3,
            'email_design_id' => $settings->email_design_id,
            'enable_email_markup' => (bool) $settings->enable_email_markup,
            'email_footer' => $settings->email_footer,
        ];
    }
}
