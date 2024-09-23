<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2024. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\DataMapper;

use Illuminate\Support\Facades\App;

class EmailTemplateDefaults
{
    public array $templates = [
        'email_template_invoice',
        'email_template_quote',
        'email_template_credit',
        'email_template_payment',
        'email_template_payment_partial',
        'email_template_statement',
        'email_template_reminder1',
        'email_template_reminder2',
        'email_template_reminder3',
        'email_template_reminder_endless',
        'email_template_custom1',
        'email_template_custom2',
        'email_template_custom3',
        'email_template_purchase_order',
        'email_template_payment_failed'
    ];

    public static function getDefaultTemplate($template, $locale)
    {
        App::setLocale($locale);

        switch ($template) {
            /* Template */

            case 'email_template_payment_failed':
                return self::emailPaymentFailedTemplate();
            case 'email_template_invoice':
                return self::emailInvoiceTemplate();
            case 'email_template_quote':
                return self::emailQuoteTemplate();
            case 'email_template_credit':
                return self::emailCreditTemplate();
            case 'email_template_payment':
                return self::emailPaymentTemplate();
            case 'email_template_payment_partial':
                return self::emailPaymentPartialTemplate();
            case 'email_template_statement':
                return self::emailStatementTemplate();
            case 'email_template_reminder1':
                return self::emailReminder1Template();
            case 'email_template_reminder2':
                return self::emailReminder2Template();
            case 'email_template_reminder3':
                return self::emailReminder3Template();
            case 'email_template_reminder_endless':
                return self::emailReminderEndlessTemplate();
            case 'email_template_custom1':
                return self::emailInvoiceTemplate();
            case 'email_template_custom2':
                return self::emailInvoiceTemplate();
            case 'email_template_custom3':
                return self::emailInvoiceTemplate();
            case 'email_template_purchase_order':
                return self::emailPurchaseOrderTemplate();
                /* Subject */
            case 'email_subject_purchase_order':
                return self::emailPurchaseOrderSubject();
            case 'email_subject_invoice':
                return self::emailInvoiceSubject();

            case 'email_subject_payment_failed':
                return self::emailPaymentFailedSubject();

            case 'email_subject_quote':
                return self::emailQuoteSubject();

            case 'email_subject_credit':
                return self::emailCreditSubject();

            case 'email_subject_payment':
                return self::emailPaymentSubject();

            case 'email_subject_payment_partial':
                return self::emailPaymentPartialSubject();

            case 'email_subject_statement':
                return self::emailStatementSubject();

            case 'email_subject_reminder1':
                return self::emailReminder1Subject();

            case 'email_subject_reminder2':
                return self::emailReminder2Subject();

            case 'email_subject_reminder3':
                return self::emailReminder3Subject();

            case 'email_subject_reminder_endless':
                return self::emailReminderEndlessSubject();

            case 'email_subject_custom1':
                return self::emailInvoiceSubject();

            case 'email_subject_custom2':
                return self::emailInvoiceSubject();

            case 'email_subject_custom3':
                return self::emailInvoiceSubject();

            case 'email_vendor_notification_subject':
                return self::emailVendorNotificationSubject();

            case 'email_vendor_notification_body':
                return self::emailVendorNotificationBody();

            case 'email_quote_template_reminder1':
                return self::emailQuoteReminder1Body();

            case 'email_quote_subject_reminder1':
                return self::emailQuoteReminder1Subject();

            default:
                return self::emailInvoiceTemplate();

        }
    }

    public static function emailPaymentFailedSubject()
    {
        return ctrans('texts.notification_invoice_payment_failed_subject', ['invoice' => '$number']);
    }

    public static function emailPaymentFailedTemplate()
    {
        return '<p>$client<br><br>'.ctrans('texts.client_payment_failure_body', ['invoice' => '$number', 'amount' => '$amount']).'</p><div>$payment_error</div><br><div>$view_button</div>';
    }

    public static function emailQuoteReminder1Subject()
    {
        return ctrans('texts.quote_reminder_subject', ['quote' => '$number', 'company' => '$company.name']);
    }

    public static function emailQuoteReminder1Body()
    {

        return '<p>$client<br><br>'.self::transformText('quote_reminder_message').'</p><div>$view_button</div>';

    }

    public static function emailVendorNotificationSubject()
    {
        return self::transformText('vendor_notification_subject');
    }

    public static function emailVendorNotificationBody()
    {
        return self::transformText('vendor_notification_body');
    }

    public static function emailInvoiceSubject()
    {
        return ctrans('texts.invoice_subject', ['number' => '$number', 'account' => '$company.name']);
    }

    public static function emailCreditSubject()
    {
        return ctrans('texts.credit_subject', ['number' => '$number', 'account' => '$company.name']);
    }

    public static function emailInvoiceTemplate()
    {
        $invoice_message = '<p>$client<br><br>'.self::transformText('invoice_message').'</p><div>$view_button</div>';

        return $invoice_message;
    }

    public static function emailInvoiceReminderTemplate()
    {
        $invoice_message = '<p>$client<br><br>'.self::transformText('reminder_message').'</p><div>$view_button</div>';

        return $invoice_message;
    }

    public static function emailQuoteSubject()
    {
        return ctrans('texts.quote_subject', ['number' => '$number', 'account' => '$company.name']);
    }

    public static function emailQuoteTemplate()
    {
        $quote_message = '<p>$client<br><br>'.self::transformText('quote_message').'</p><div>$view_button</div>';

        return $quote_message;
    }

    public static function emailPaymentSubject()
    {
        return ctrans('texts.payment_subject');
    }

    public static function emailPurchaseOrderSubject()
    {
        return ctrans('texts.purchase_order_subject', ['number' => '$number', 'account' => '$account']);
    }

    public static function emailPurchaseOrderTemplate()
    {
        $purchase_order_message = '<p>$vendor<br><br>'.self::transformText('purchase_order_message').'</p><div>$view_button</div>';

        return $purchase_order_message;
    }

    public static function emailPaymentTemplate()
    {
        $payment_message = '<p>$client<br><br>'.self::transformText('payment_message').'<br><br>$invoices</p><div>$view_button</div>';

        return $payment_message;
    }

    public static function emailCreditTemplate()
    {
        $credit_message = '<p>$client<br><br>'.self::transformText('credit_message').'</p><div>$view_button</div>';

        return $credit_message;
    }

    public static function emailPaymentPartialTemplate()
    {
        $payment_message = '<p>$client<br><br>'.self::transformText('payment_message').'<br><br>$invoices</p><div>$view_button</div>';

        return $payment_message;
    }

    public static function emailPaymentPartialSubject()
    {
        return ctrans('texts.payment_subject');
    }

    public static function emailReminder1Subject()
    {
        return ctrans('texts.reminder_subject', ['invoice' => '$number', 'account' => '$company.name']);
    }

    public static function emailReminder1Template()
    {
        return self::emailInvoiceReminderTemplate();
    }

    public static function emailReminder2Subject()
    {
        return ctrans('texts.reminder_subject', ['invoice' => '$number', 'account' => '$company.name']);
    }

    public static function emailReminder2Template()
    {
        return self::emailInvoiceReminderTemplate();
    }

    public static function emailReminder3Subject()
    {
        return ctrans('texts.reminder_subject', ['invoice' => '$number', 'account' => '$company.name']);
    }

    public static function emailReminder3Template()
    {
        return self::emailInvoiceReminderTemplate();
    }

    public static function emailReminderEndlessSubject()
    {
        return ctrans('texts.reminder_subject', ['invoice' => '$number', 'account' => '$company.name']);
    }

    public static function emailReminderEndlessTemplate()
    {
        return self::emailInvoiceReminderTemplate();
    }

    public static function emailStatementSubject()
    {
        return ctrans('texts.your_statement');
    }

    public static function emailStatementTemplate()
    {
        $statement_message = '<p>$client<br><br>'.self::transformText('client_statement_body').'<br></p>';

        return $statement_message;

        // return ctrans('texts.client_statement_body', ['start_date' => '$start_date', 'end_date' => '$end_date']);
    }

    private static function transformText($string)
    {
        //preformat the string, removing trailing colons.

        return str_replace(':', '$', rtrim(ctrans('texts.'.$string), ':'));
    }
}
