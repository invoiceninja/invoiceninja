<?php

/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2022. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Utils\Traits;

use App\DataMapper\EmailTemplateDefaults;
use App\Utils\Ninja;
use App\Utils\SystemHealth;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

trait AppSetup
{
    public function checkAppSetup()
    {
        if (Ninja::isNinja()) {  // Is this the invoice ninja production system?
            return Ninja::isNinja();
        }

        $check = SystemHealth::check();

        return $check['system_health'] == 'true';
    }

    public function buildCache($force = false)
    {
        $cached_tables = config('ninja.cached_tables');

        foreach ($cached_tables as $name => $class) {
            if (! Cache::has($name) || $force) {

                // check that the table exists in case the migration is pending
                if (! Schema::hasTable((new $class())->getTable())) {
                    continue;
                }
                if ($name == 'payment_terms') {
                    $orderBy = 'num_days';
                } elseif ($name == 'fonts') {
                    $orderBy = 'sort_order';
                } elseif (in_array($name, ['currencies', 'industries', 'languages', 'countries', 'banks', 'timezones'])) {
                    $orderBy = 'name';
                } else {
                    $orderBy = 'id';
                }
                $tableData = $class::orderBy($orderBy)->get();
                if ($tableData->count() > 1) {
                    Cache::forever($name, $tableData);
                }
            }
        }

        /*Build template cache*/
        $this->buildTemplates();
    }

    private function buildTemplates($name = 'templates')
    {
        $data = [

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
            'payment_partial' => [
                'subject' => EmailTemplateDefaults::emailPaymentPartialSubject(),
                'body' => EmailTemplateDefaults::emailPaymentPartialTemplate(),
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
            'credit' => [
                'subject' => EmailTemplateDefaults::emailCreditSubject(),
                'body' => EmailTemplateDefaults::emailCreditTemplate(),
            ],
            'purchase_order' => [
                'subject' => EmailTemplateDefaults::emailPurchaseOrderSubject(),
                'body' => EmailTemplateDefaults::emailPurchaseOrderTemplate(),
            ],
        ];

        Cache::forever($name, $data);
    }

    private function updateEnvironmentProperty(string $property, $value): void
    {
        // if (Str::contains($value, '#')) {
        //     $value = sprintf('"%s"', $value);
        // }

        $env = file(base_path('.env'));

        $position = null;

        foreach ((array) $env as $key => $variable) {
            if (Str::startsWith($variable, $property)) {
                $position = $key;
            }
        }

        $words_count = count(explode(' ', trim($value)));

        if (is_null($position)) {
            $words_count > 1 ? $env[] = "{$property}=".'"'.$value.'"'."\n" : $env[] = "{$property}=".$value."\n";
        } else {
            $env[$position] = "{$property}=".'"'.$value.'"'."\n"; // If value of variable is more than one word, surround with quotes.
        }

        try {
            file_put_contents(base_path('.env'), $env);
        } catch (\Exception $e) {
            info($e->getMessage());
        }
    }
}
