<?php

namespace App\Models;

use Eloquent;

/**
 * Class Account.
 */
class AccountEmailSettings extends Eloquent
{
    /**
     * @var array
     */
    protected $fillable = [
        'bcc_email',
        'reply_to_email',
        'email_subject_invoice',
        'email_subject_quote',
        'email_subject_payment',
        'email_template_invoice',
        'email_template_quote',
        'email_template_payment',
        'email_subject_reminder1',
        'email_subject_reminder2',
        'email_subject_reminder3',
        'email_template_reminder1',
        'email_template_reminder2',
        'email_template_reminder3',
        'late_fee1_amount',
        'late_fee1_percent',
        'late_fee2_amount',
        'late_fee2_percent',
        'late_fee3_amount',
        'late_fee3_percent',
        'enable_reminder1',
        'enable_reminder2',
        'enable_reminder3',
        'enable_reminder4',
        'num_days_reminder1',
        'num_days_reminder2',
        'num_days_reminder3',
        'direction_reminder1',
        'direction_reminder2',
        'direction_reminder3',
        'field_reminder1',
        'field_reminder2',
        'field_reminder3',
        'email_subject_quote_reminder1',
        'email_subject_quote_reminder2',
        'email_subject_quote_reminder3',
        'email_template_quote_reminder1',
        'email_template_quote_reminder2',
        'email_template_quote_reminder3',
        'late_fee_quote1_amount',
        'late_fee_quote1_percent',
        'late_fee_quote2_amount',
        'late_fee_quote2_percent',
        'late_fee_quote3_amount',
        'late_fee_quote3_percent',
        'enable_quote_reminder1',
        'enable_quote_reminder2',
        'enable_quote_reminder3',
        'enable_quote_reminder4',
        'num_days_quote_reminder1',
        'num_days_quote_reminder2',
        'num_days_quote_reminder3',
        'direction_quote_reminder1',
        'direction_quote_reminder2',
        'direction_quote_reminder3',
        'field_quote_reminder1',
        'field_quote_reminder2',
        'field_quote_reminder3',
        'email_design_id',
        'enable_email_markup',
        'email_footer',
    ];

    public static $templates = [
        TEMPLATE_INVOICE,
        TEMPLATE_QUOTE,
        TEMPLATE_PROPOSAL,
        //TEMPLATE_PARTIAL,
        TEMPLATE_PAYMENT,
        TEMPLATE_REMINDER1,
        TEMPLATE_REMINDER2,
        TEMPLATE_REMINDER3,
        TEMPLATE_REMINDER4,
        TEMPLATE_QUOTE_REMINDER1,
        TEMPLATE_QUOTE_REMINDER2,
        TEMPLATE_QUOTE_REMINDER3,
        TEMPLATE_QUOTE_REMINDER4,
    ];

}
