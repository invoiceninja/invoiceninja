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

namespace App\Http\Middleware;

use App\DataMapper\EmailTemplateDefaults;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

/**
 * Class StartupCheck.
 */
class StartupCheck
{
    /**
     * Handle an incoming request.
     * @deprecated
     * @param Request $request
     * @param Closure $next
     *
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        // $start = microtime(true);

        /* Make sure our cache is built */
        $cached_tables = config('ninja.cached_tables');

        foreach ($cached_tables as $name => $class) {
            if ($request->has('clear_cache') || ! Cache::has($name)) {

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
        if ($request->has('clear_cache') || ! Cache::has('templates')) {
            $this->buildTemplates();
        }

        return $next($request);
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
