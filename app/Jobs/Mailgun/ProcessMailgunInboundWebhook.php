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

namespace App\Jobs\PostMark;

use App\Helpers\Mail\Webhook\Maigun\MailgunWebhookHandler;
use App\Libraries\MultiDB;
use App\Models\SystemLog;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ProcessMailgunInboundWebhook implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 1;

    public $invitation;

    private $entity;

    private array $default_response = [
        'recipients' => '',
        'subject' => 'Message not found.',
        'entity' => '',
        'entity_id' => '',
        'events' => [],
    ];

    /**
     * Create a new job instance.
     *
     */
    public function __construct(private array $request)
    {
    }

    private function getSystemLog(string $message_id): ?SystemLog
    {
        return SystemLog::query()
            ->where('company_id', $this->invitation->company_id)
            ->where('type_id', SystemLog::TYPE_WEBHOOK_RESPONSE)
            ->whereJsonContains('log', ['MessageID' => $message_id])
            ->orderBy('id', 'desc')
            ->first();

    }

    private function updateSystemLog(SystemLog $system_log, array $data): void
    {
        $system_log->log = $data;
        $system_log->save();
    }

    /**
     * Execute the job.
     *
     *
     * @return void
     */
    public function handle()
    {
        // match companies
        if (array_key_exists('ToFull', $this->request))
            throw new \Exception('invalid body');

        foreach ($this->request['ToFull'] as $toEmailEntry) {
            $toEmail = $toEmailEntry['Email'];

            $company = MultiDB::findAndSetDbByExpenseMailbox($toEmail);
            if (!$company) {
                nlog('unknown Expense Mailbox occured while handling an inbound email from postmark: ' . $toEmail);
                continue;
            }

            (new MailgunWebhookHandler())->process($this->request);
        }
    }
}
