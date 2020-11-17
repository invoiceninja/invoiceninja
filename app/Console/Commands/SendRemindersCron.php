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

namespace App\Console\Commands;

use App\Jobs\Ninja\SendReminders;
use App\Jobs\Util\WebHookHandler;
use App\Models\Invoice;
use App\Models\Quote;
use App\Models\Webhook;
use Illuminate\Console\Command;

class SendRemindersCron extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'ninja:send-reminders';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Force send all reminders';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        SendReminders::dispatchNow();

        $this->webHookOverdueInvoices();
        $this->webHookExpiredQuotes();
    }

    private function webHookOverdueInvoices()
    {
        $invoices = Invoice::where('is_deleted', 0)
                          ->whereIn('status_id', [Invoice::STATUS_SENT, Invoice::STATUS_PARTIAL])
                          ->where('balance', '>', 0)
                          ->whereDate('due_date', now()->subDays(1)->startOfDay())
                          ->cursor();
    
        $invoices->each(function ($invoice){
            WebHookHandler::dispatch(Webhook::EVENT_LATE_INVOICE, $invoice, $invoice->company);
        });

    }

    private function webHookExpiredQuotes()
    {
        $quotes = Quote::where('is_deleted', 0)
                          ->where('status_id', Quote::STATUS_SENT)
                          ->whereDate('due_date', now()->subDays(1)->startOfDay())
                          ->cursor();
    
        $quotes->each(function ($quote){
            WebHookHandler::dispatch(Webhook::EVENT_EXPIRED_QUOTE, $quote, $quote->company);
        });
    }
}
