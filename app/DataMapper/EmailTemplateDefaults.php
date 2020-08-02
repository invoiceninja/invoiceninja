<?php
/**
 * Invoice Ninja (https://invoiceninja.com)
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2020. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://opensource.org/licenses/AAL
 */

namespace App\DataMapper;

use Illuminate\Support\Facades\App;
use League\CommonMark\CommonMarkConverter;

class EmailTemplateDefaults
{
    public static function getDefaultTemplate($template, $locale)
    {
        App::setLocale($locale);

        switch ($template) {

            /* Template */
            
            case 'email_template_invoice':
                return self::emailInvoiceTemplate();
                break;
            case 'email_template_quote':
                return self::emailQuoteTemplate();
                break;
            case 'email_template_payment':
                return self::emailPaymentTemplate();
                break;
            case 'email_template_payment_partial':
                return self::emailPaymentTemplate();
                break;
            case 'email_template_statement':
                return self::emailStatementTemplate();
                break;
            case 'email_template_reminder1':
                return self::emailReminder1Template();
                break;
            case 'email_template_reminder2':
                return self::emailReminder2Template();
                break;
            case 'email_template_reminder3':
                return self::emailReminder3Template();
                break;
            case 'email_template_reminder_endless':
                return self::emailReminderEndlessTemplate();
                break;
            case 'email_template_custom1':
                return self::emailInvoiceTemplate();
                break;
            case 'email_template_custom2':
                return self::emailInvoiceTemplate();
                break;
            case 'email_template_custom3':
                return self::emailInvoiceTemplate();
                break;

            /* Subject */
            
            case 'email_subject_invoice':
                return self::emailInvoiceSubject();
                break;
            case 'email_subject_quote':
                return self::emailQuoteSubject();
                break;
            case 'email_subject_payment':
                return self::emailPaymentSubject();
                break;
            case 'email_subject_payment_partial':
                return self::emailPaymentSubject();
                break;
            case 'email_subject_statement':
                return self::emailStatementSubject();
                break;
            case 'email_subject_reminder1':
                return self::emailReminder1Subject();
                break;
            case 'email_subject_reminder2':
                return self::emailReminder2Subject();
                break;
            case 'email_subject_reminder3':
                return self::emailReminder3Subject();
                break;
            case 'email_subject_reminder_endless':
                return self::emailReminderEndlessSubject();
                break;
            case 'email_subject_custom1':
                return self::emailInvoiceSubject();
                break;
            case 'email_subject_custom2':
                return self::emailInvoiceSubject();
                break;
            case 'email_subject_custom3':
                return self::emailInvoiceSubject();
                break;

            default:
                return self::emailInvoiceTemplate();
                break;
        }
    }

    public static function emailInvoiceSubject()
    {
        return ctrans('texts.invoice_subject', ['number'=>'$number', 'account'=>'$company.name']);
        //return Parsedown::instance()->line(self::transformText('invoice_subject'));
    }

    public static function emailInvoiceTemplate()
    {
        $converter = new CommonMarkConverter([
            'html_input' => 'strip',
            'allow_unsafe_links' => false,
        ]);

        $invoice_message = '<p>' . self::transformText('invoice_message') . '</p><br><br><p>$view_link</p>';
        return $invoice_message;
        //return $converter->convertToHtml($invoice_message);

    }

    public static function emailQuoteSubject()
    {
        return ctrans('texts.quote_subject', ['number'=>'$number', 'account'=>'$company.name']);

        //return Parsedown::instance()->line(self::transformText('quote_subject'));
    }

    public static function emailQuoteTemplate()
    {
        $converter = new CommonMarkConverter([
            'html_input' => 'strip',
            'allow_unsafe_links' => false,
        ]);

        return $converter->convertToHtml(self::transformText('quote_message'));
        //return Parsedown::instance()->line(self::transformText('quote_message'));
    }

    public static function emailPaymentSubject()
    {
        return ctrans('texts.payment_subject');
        //return Parsedown::instance()->line(self::transformText('payment_subject'));
    }

    public static function emailPaymentTemplate()
    {
        return Parsedown::instance()->line(self::transformText('payment_message'));
    }

    public static function emailReminder1Subject()
    {
        return ctrans('texts.reminder_subject', ['invoice'=>'$invoice.number', 'account'=>'$company.name']);
    }

    public static function emailReminder1Template()
    {
        //  return Parsedown::instance()->line('First Email Reminder Text');
    }

    public static function emailReminder2Subject()
    {
        return ctrans('texts.reminder_subject', ['invoice'=>'$invoice.number', 'account'=>'$company.name']);
//        return Parsedown::instance()->line(self::transformText('reminder_subject'));
    }

    public static function emailReminder2Template()
    {
        //  return Parsedown::instance()->line('Second Email Reminder Text');
    }

    public static function emailReminder3Subject()
    {
        return ctrans('texts.reminder_subject', ['invoice'=>'$invoice.number', 'account'=>'$company.name']);
//        return Parsedown::instance()->line(self::transformText('reminder_subject'));
    }

    public static function emailReminder3Template()
    {
        //  return Parsedown::instance()->line('Third Email Reminder Text');
    }

    public static function emailReminderEndlessSubject()
    {
        return ctrans('texts.reminder_subject', ['invoice'=>'$invoice.number', 'account'=>'$company.name']);
//        return Parsedown::instance()->line(self::transformText('reminder_subject'));
    }

    public static function emailReminderEndlessTemplate()
    {
        return Parsedown::instance()->line('Endless Email Reminder Text');
    }

    public static function emailStatementSubject()
    {
        return Parsedown::instance()->line('Statement Subject needs texts record!');
    }

    public static function emailStatementTemplate()
    {
        return Parsedown::instance()->line('Statement Templates needs texts record!');
    }


    private static function transformText($string)
    {
        return str_replace(":", "$", ctrans('texts.'.$string));
    }
}
