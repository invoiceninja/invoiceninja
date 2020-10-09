<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2020. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://opensource.org/licenses/AAL
 */

namespace App\Utils\Traits;

use App\DataMapper\EmailTemplateDefaults;
use App\Utils\Ninja;
use App\Utils\SystemHealth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

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

    public function buildCache()
    {
        $cached_tables = config('ninja.cached_tables');

        foreach ($cached_tables as $name => $class) {
            if (request()->has('clear_cache') || ! Cache::has($name)) {

                // check that the table exists in case the migration is pending
                if (! Schema::hasTable((new $class())->getTable())) {
                    continue;
                }
                if ($name == 'payment_terms') {
                    $orderBy = 'num_days';
                } elseif ($name == 'fonts') {
                    $orderBy = 'sort_order';
                } elseif (in_array($name, ['currencies', 'industries', 'languages', 'countries', 'banks'])) {
                    $orderBy = 'name';
                } else {
                    $orderBy = 'id';
                }
                $tableData = $class::orderBy($orderBy)->get();
                if ($tableData->count()) {
                    Cache::forever($name, $tableData);
                }
            }
        }

        /*Build template cache*/
        if (request()->has('clear_cache') || ! Cache::has('templates')) 
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

        Cache::forever($name, $data);
    }
}
