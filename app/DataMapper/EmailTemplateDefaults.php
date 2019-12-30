<?php
/**
 * Invoice Ninja (https://invoiceninja.com)
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2019. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://opensource.org/licenses/AAL
 */

namespace App\DataMapper;

use Parsedown;

class EmailTemplateDefaults
{
    public static function emailInvoiceSubject()
    {
        return ctrans('texts.invoice_subject', ['number'=>'$number', 'account'=>'$company.name']);
        //return Parsedown::instance()->line(self::transformText('invoice_subject'));
    }

    public static function emailInvoiceTemplate()
    {
        return Parsedown::instance()->line(self::transformText('invoice_message'));
    }

    public static function emailQuoteSubject()
    {
        return ctrans('texts.quote_subject', ['number'=>'$number', 'account'=>'$company.name']);

        //return Parsedown::instance()->line(self::transformText('quote_subject'));
    }

    public static function emailQuoteTemplate()
    {
        return Parsedown::instance()->line(self::transformText('quote_message'));
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
        return Parsedown::instance()->line('First Email Reminder Text');
    }

    public static function emailReminder2Subject()
    {
        return ctrans('texts.reminder_subject', ['invoice'=>'$invoice.number', 'account'=>'$company.name']);
//        return Parsedown::instance()->line(self::transformText('reminder_subject'));
    }

    public static function emailReminder2Template()
    {
        return Parsedown::instance()->line('Second Email Reminder Text');
    }

    public static function emailReminder3Subject()
    {
        return ctrans('texts.reminder_subject', ['invoice'=>'$invoice.number', 'account'=>'$company.name']);
//        return Parsedown::instance()->line(self::transformText('reminder_subject'));
    }

    public static function emailReminder3Template()
    {
        return Parsedown::instance()->line('Third Email Reminder Text');
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
