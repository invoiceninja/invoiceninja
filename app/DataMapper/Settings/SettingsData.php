<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2023. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\DataMapper\Settings;

class SettingsData
{
    public bool $auto_archive_invoice = false; // @implemented

    public string $qr_iban = ''; //@implemented

    public string $besr_id = ''; //@implemented

    public string $lock_invoices = 'off'; // off, when_sent, when_paid //@implemented

    public bool $enable_client_portal_tasks = false; //@ben to implement

    public string $show_all_tasks_client_portal = 'invoiced'; // all, uninvoiced, invoiced

    public bool $enable_client_portal_password = false; //@implemented

    public bool $enable_client_portal = true; //@implemented

    public bool $enable_client_portal_dashboard = false; // @TODO There currently is no dashboard, so this is pending

    public bool $signature_on_pdf = false; //@implemented

    public bool $document_email_attachment = false; //@TODO I assume this is 3rd party attachments on the entity to be included

    public string $portal_design_id = '1'; //? @deprecated

    public string $timezone_id = ''; //@implemented

    public string $date_format_id = ''; //@implemented

    public bool $military_time = false; // @TODO Implemented in Tasks only?

    public string $language_id = ''; //@implemented

    public bool $show_currency_code = false; //@implemented

    public string $company_gateway_ids = ''; //@implemented

    public string $currency_id = '1'; //@implemented

    public string $custom_value1 = ''; //@implemented

    public string $custom_value2 = ''; //@implemented

    public string $custom_value3 = ''; //@implemented

    public string $custom_value4 = ''; //@implemented

    public float $default_task_rate = 0; // @TODO Where do we inject this?

    public string $payment_terms = ''; //@implemented

    public bool $send_reminders = true; //@TODO

    public string $custom_message_dashboard = ''; // @TODO There currently is no dashboard, so this is pending

    public string $custom_message_unpaid_invoice = '';

    public string $custom_message_paid_invoice = '';

    public string $custom_message_unapproved_quote = '';

    public bool $auto_archive_quote = false; //@implemented

    public bool $auto_convert_quote = true; //@implemented

    public bool $auto_email_invoice = true; //@only used for Recurring Invoices, if set to false, we never send?

    public int $entity_send_time = 6;

    public bool $inclusive_taxes = false; //@implemented

    public string $quote_footer = ''; //@implemented

    public object $translations;

    public string $counter_number_applied = 'when_saved'; // when_saved, when_sent //@implemented

    public string $quote_number_applied = 'when_saved'; // when_saved, when_sent //@implemented

    public string $invoice_number_pattern = ''; //@implemented

    public int $invoice_number_counter = 1; //@implemented

    public string $recurring_invoice_number_pattern = ''; //@implemented

    public int $recurring_invoice_number_counter = 1; //@implemented

    public string $quote_number_pattern = ''; //@implemented

    public int $quote_number_counter = 1; //@implemented

    public string $client_number_pattern = ''; //@implemented

    public int $client_number_counter = 1; //@implemented

    public string $credit_number_pattern = ''; //@implemented

    public int $credit_number_counter = 1; //@implemented

    public string $task_number_pattern = ''; //@implemented

    public int $task_number_counter = 1; //@implemented

    public string $expense_number_pattern = ''; //@implemented

    public int $expense_number_counter = 1; //@implemented

    public string $recurring_expense_number_pattern = '';

    public int $recurring_expense_number_counter = 1;

    public string $recurring_quote_number_pattern = '';

    public int $recurring_quote_number_counter = 1;

    public string $vendor_number_pattern = ''; //@implemented

    public int $vendor_number_counter = 1; //@implemented

    public string $ticket_number_pattern = ''; //@implemented

    public int $ticket_number_counter = 1; //@implemented

    public string $payment_number_pattern = ''; //@implemented

    public int $payment_number_counter = 1; //@implemented

    public string $project_number_pattern = ''; //@implemented

    public int $project_number_counter = 1; //@implemented

    public string $purchase_order_number_pattern = ''; //@implemented

    public int $purchase_order_number_counter = 1; //@implemented

    public bool $shared_invoice_quote_counter = false; //@implemented

    public bool $shared_invoice_credit_counter = false; //@implemented

    public string $recurring_number_prefix = ''; //@implemented

    public string $reset_counter_frequency_id = '0'; //@implemented

    public string $reset_counter_date = ''; //@implemented

    public int $counter_padding = 4; //@implemented

    public string $auto_bill = 'off'; // off, always, opt-in, opt-out //@implemented

    public string $auto_bill_date = 'on_due_date'; // on_due_date, on_send_date //@implemented

    public string $invoice_terms = ''; //@implemented

    public string $quote_terms = ''; //@implemented

    public int $invoice_taxes = 0; // ? used in AP only?

    public string $invoice_design_id = 'Wpmbk5ezJn'; //@implemented

    public string $quote_design_id = 'Wpmbk5ezJn'; //@implemented

    public string $credit_design_id = 'Wpmbk5ezJn'; //@implemented

    public string $purchase_order_design_id = 'Wpmbk5ezJn';

    public string $purchase_order_footer = ''; //@implemented

    public string $purchase_order_terms = ''; //@implemented

    public string $purchase_order_public_notes = ''; //@implemented

    public bool $require_purchase_order_signature = false;  //@TODO ben to confirm

    public string $invoice_footer = ''; //@implemented

    public string $credit_footer = ''; //@implemented

    public string $credit_terms = ''; //@implemented

    public string $invoice_labels = ''; //@TODO used in AP only?

    public string $tax_name1 = ''; //@TODO where do we use this?

    public float $tax_rate1 = 0; //@TODO where do we use this?

    public string $tax_name2 = ''; //@TODO where do we use this?

    public float $tax_rate2 = 0; //@TODO where do we use this?

    public string $tax_name3 = ''; //@TODO where do we use this?

    public float $tax_rate3 = 0; //@TODO where do we use this?

    public string $payment_type_id = '0'; //@TODO where do we use this?

    public string $valid_until = ''; //@implemented

    public bool $show_accept_invoice_terms = false; //@TODO ben to confirm

    public bool $show_accept_quote_terms = false;  //@TODO ben to confirm

    public string $email_sending_method = 'default'; // enum 'default', 'gmail', 'office365', 'client_postmark', 'client_mailgun' //@implemented

    public string $gmail_sending_user_id = '0'; //@implemented

    public string $reply_to_email = ''; //@implemented

    public string $reply_to_name = ''; //@implemented

    public string $bcc_email = ''; //@TODO

    public bool $pdf_email_attachment = false; //@implemented

    public bool $ubl_email_attachment = false; //@implemented

    public string $email_style = 'light'; // plain, light, dark, custom  //@implemented

    public string $email_style_custom = '';      // the template itself  //@implemented

    public string $email_subject_invoice = '';  //@implemented

    public string $email_subject_quote = '';  //@implemented

    public string $email_subject_credit = ''; //@implemented

    public string $email_subject_payment = ''; //@implemented

    public string $email_subject_payment_partial = ''; //@implemented

    public string $email_subject_statement = ''; //@implemented

    public string $email_subject_purchase_order = ''; //@implemented

    public string $email_template_purchase_order = ''; //@implemented

    public string $email_template_invoice = ''; //@implemented

    public string $email_template_credit = ''; //@implemented

    public string $email_template_quote = ''; //@implemented

    public string $email_template_payment = ''; //@implemented

    public string $email_template_payment_partial = ''; //@implemented

    public string $email_template_statement = ''; //@implemented

    public string $email_subject_reminder1 = ''; //@implemented

    public string $email_subject_reminder2 = ''; //@implemented

    public string $email_subject_reminder3 = ''; //@implemented

    public string $email_subject_reminder_endless = ''; //@implemented

    public string $email_template_reminder1 = ''; //@implemented

    public string $email_template_reminder2 = ''; //@implemented

    public string $email_template_reminder3 = ''; //@implemented

    public string $email_template_reminder_endless = ''; //@implemented

    public string $email_signature = ''; //@implemented

    public bool $enable_email_markup = true; //@TODO -

    public string $email_subject_custom1 = ''; //@TODO

    public string $email_subject_custom2 = ''; //@TODO

    public string $email_subject_custom3 = ''; //@TODO

    public string $email_template_custom1 = ''; //@TODO

    public string $email_template_custom2 = ''; //@TODO

    public string $email_template_custom3 = ''; //@TODO

    public bool $enable_reminder1 = false; //@implmemented

    public bool $enable_reminder2 = false; //@implmemented

    public bool $enable_reminder3 = false; //@implmemented

    public bool $enable_reminder_endless = false; //@implmemented

    public int $num_days_reminder1 = 0; //@implmemented

    public int $num_days_reminder2 = 0; //@implmemented

    public int $num_days_reminder3 = 0; //@implmemented

    public string $schedule_reminder1 = ''; // (enum: after_invoice_date, before_due_date, after_due_date) implmemented

    public string $schedule_reminder2 = ''; // (enum: after_invoice_date, before_due_date, after_due_date) implmemented

    public string $schedule_reminder3 = ''; // (enum: after_invoice_date, before_due_date, after_due_date) implmemented

    public int $reminder_send_time = 0; // number of seconds from UTC +0 to send reminders @TODO

    public float $late_fee_amount1 = 0; //@implemented

    public float $late_fee_amount2 = 0; //@implemented

    public float $late_fee_amount3 = 0; //@implemented

    public float $late_fee_percent1 = 0; //@implemented

    public float $late_fee_percent2 = 0; //@implemented

    public float $late_fee_percent3 = 0; //@implemented

    public string $endless_reminder_frequency_id = '0'; //@implemented

    public float $late_fee_endless_amount = 0; //@implemented

    public float $late_fee_endless_percent = 0; //@implemented

    public bool $client_online_payment_notification = true; //@todo implement in notifications check this bool prior to sending payment notification to client

    public bool $client_manual_payment_notification = true; //@todo implement in notifications check this bool prior to sending manual payment notification to client

    public string $name = ''; //@implemented

    public string $company_logo = ''; //@implemented

    public string $website = ''; //@implemented

    public string $address1 = ''; //@implemented

    public string $address2 = ''; //@implemented

    public string $city = ''; //@implemented

    public string $state = ''; //@implemented

    public string $postal_code = ''; //@implemented

    public string $phone = ''; //@implemented

    public string $email = ''; //@implemented

    public string $country_id = ''; //@implemented

    public string $vat_number = ''; //@implemented

    public string $id_number = ''; //@implemented

    public string $page_size = 'A4';  // Letter, Legal, Tabloid, Ledger, A0, A1, A2, A3, A4, A5, A6

    public string $page_layout = 'portrait';

    public int $font_size = 16; //@implemented

    public string $primary_font = 'Roboto';

    public string $secondary_font = 'Roboto';

    public string $primary_color = '#298AAB';

    public string $secondary_color = '#7081e0';

    public bool $page_numbering = false;

    public string $page_numbering_alignment = 'C';  // C, R, L

    public bool $hide_paid_to_date = false; //@TODO where?

    public bool $embed_documents = false; //@TODO where?

    public bool $all_pages_header = false; //@deprecated 31-05-2021

    public bool $all_pages_footer = false; //@deprecated 31-05-2021

    public string $pdf_variables = ''; //@implemented

    public string $portal_custom_head = ''; //@TODO @BEN

    public string $portal_custom_css = ''; //@TODO @BEN

    public string $portal_custom_footer = ''; //@TODO @BEN

    public string $portal_custom_js = ''; //@TODO @BEN

    public bool $client_can_register = false; //@deprecated 04/06/2021

    public string $client_portal_terms = ''; //@TODO @BEN

    public string $client_portal_privacy_policy = ''; //@TODO @BEN

    public bool $client_portal_enable_uploads = false; //@implemented

    public bool $client_portal_allow_under_payment = false; //@implemented

    public float $client_portal_under_payment_minimum = 0; //@implemented

    public bool $client_portal_allow_over_payment = false; //@implemented

    public string $use_credits_payment = 'off'; // always, option, off //@implemented

    public bool $hide_empty_columns_on_pdf = false;

    public string $email_from_name = '';

    public bool $auto_archive_invoice_cancelled = false;

    public bool $vendor_portal_enable_uploads = false;

    public bool $send_email_on_mark_paid = false;

    public string $postmark_secret = '';

    public string $custom_sending_email = '';

    public string $mailgun_secret = '';

    public string $mailgun_domain = '';

    public string $mailgun_endpoint = 'api.mailgun.net'; // api.eu.mailgun.net

    public bool $auto_bill_standard_invoices = false;

    public string $email_alignment = 'center'; // center, left, right

    public bool $show_email_footer = true;

    public string $company_logo_size = '';

    public bool $show_paid_stamp = false;

    public bool $show_shipping_address = false;

    public bool $accept_client_input_quote_approval = false;

    public bool $allow_billable_task_items = true;

    public bool $show_task_item_description = false;

    public bool $client_initiated_payments = false;

    public float $client_initiated_payments_minimum = 0;

    public bool $sync_invoice_quote_columns = true;

    public string $e_invoice_type = 'EN16931';

    public string $default_expense_payment_type_id = '0';

    public bool $enable_e_invoice = false;

    public string $classification = '';

    private mixed $object;

    public function cast(mixed $object)
    {
        if(is_array($object)) {
            $object = (object)$object;
        }

        if (is_object($object)) {
            foreach ($object as $key => $value) {

                try {
                    settype($object->{$key}, gettype($this->{$key}));
                } catch(\Exception | \Error | \Throwable $e) {

                    if(property_exists($this, $key)) {
                        $object->{$key} = $this->{$key};
                    } else {
                        unset($object->{$key});
                    }

                }

                // if(!property_exists($this, $key)) {
                //     unset($object->{$key});
                // }
                // elseif(is_array($object->{$key}) && gettype($this->{$key} != 'array')){
                //     $object->{$key} = $this->{$key};
                // }
                // else {
                //     settype($object->{$key}, gettype($this->{$key}));
                // }
            }
        }
        $this->object = $object;

        return $this;
    }

    public function toObject(): object
    {
        return (object)$this->object;
    }

    public function toArray(): array
    {
        return (array)$this->object;
    }
}
