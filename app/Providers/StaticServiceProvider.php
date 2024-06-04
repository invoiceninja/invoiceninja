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

namespace App\Providers;

use App\Models\Bank;
use App\Models\Size;
use App\Models\Country;
use App\Models\Gateway;
use App\Models\Currency;
use App\Models\Industry;
use App\Models\Language;
use App\Models\Timezone;
use App\Models\DateFormat;
use App\Models\PaymentTerm;
use Illuminate\Support\ServiceProvider;
use App\DataMapper\EmailTemplateDefaults;

class StaticServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     *
     * @return void
     */
    public function register()
    {

            
        app()->singleton('currencies', function ($app) {
            return Currency::query()->orderBy('name')->get();
        });

        app()->singleton('languages', function ($app) {
            return Language::query()->orderBy('name')->get();
        });

        app()->singleton('countries', function ($app) {
            return Country::query()->orderBy('name')->get();
        });

        app()->singleton('payment_types', function ($app) {
            return PaymentTerm::query()->orderBy('num_days')->get();
        });

        app()->singleton('industries', function ($app) {
            return Industry::query()->orderBy('name')->get();
        });

        app()->singleton('banks', function ($app) {
            return Bank::query()->orderBy('name')->get();
        });

        app()->singleton('date_formats', function ($app) {
            return DateFormat::query()->orderBy('id')->get();
        });

        app()->singleton('timezones', function ($app) {
            return Timezone::query()->orderBy('id')->get();
        });

        app()->singleton('gateways', function ($app) {
            return Gateway::query()->orderBy('id')->get();
        });

        app()->singleton('industries', function ($app) {
            return Industry::query()->orderBy('id')->get();
        });

        app()->singleton('sizes', function ($app) {
            return Size::query()->orderBy('id')->get();
        });

        /** @deprecated */
        app()->singleton('banks', function ($app) {
            return Bank::query()->orderBy('id')->get();
        });

        app()->singleton('templates', function ($app) {
            return [
                'invoice' => [
                    'subject' => EmailTemplateDefaults::emailInvoiceSubject(),
                    'body' => EmailTemplateDefaults::emailInvoiceTemplate(),
                ],
                'quote' => [
                    'subject' => EmailTemplateDefaults::emailQuoteSubject(),
                    'body' => EmailTemplateDefaults::emailQuoteTemplate(),
                ],
                'payment' => [
                    'subject' => EmailTemplateDefaults::emailPaymentSubject(),
                    'body' => EmailTemplateDefaults::emailPaymentTemplate(),
                ],
                'reminder1' => [
                    'subject' => EmailTemplateDefaults::emailReminder1Subject(),
                    'body' => EmailTemplateDefaults::emailReminder1Template(),
                ],
                'reminder2' => [
                    'subject' => EmailTemplateDefaults::emailReminder2Subject(),
                    'body' => EmailTemplateDefaults::emailReminder2Template(),
                ],
                'reminder3' => [
                    'subject' => EmailTemplateDefaults::emailReminder3Subject(),
                    'body' => EmailTemplateDefaults::emailReminder3Template(),
                ],
                'reminder_endless' => [
                    'subject' => EmailTemplateDefaults::emailReminderEndlessSubject(),
                    'body' => EmailTemplateDefaults::emailReminderEndlessTemplate(),
                ],
                'statement' => [
                    'subject' => EmailTemplateDefaults::emailStatementSubject(),
                    'body' => EmailTemplateDefaults::emailStatementTemplate(),
                ],
            ];

        });

    }

    public function boot()
    {

    }
}