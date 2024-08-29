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

namespace App\Services\EDocument\Jobes;

use App\Libraries\MultiDB;
use App\Models\Invoice;
use App\Utils\Ninja;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\Middleware\WithoutOverlapping;

class SendEDocument implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public $tries = 2;
    
    public $deleteWhenMissingModels = true;

    public function __construct(private string $entity, private int $id, private string $db)
    {
    }

    public function backoff()
    {
        return [rand(5, 29), rand(30, 59)];
    }

    public function handle()
    {
        MultiDB::setDB($this->db);

        $model = $this->entity::find($this->id);
        $e_invoice_standard = $model->client ? $model->client->getSetting('e_invoice_type') : $model->company->getSetting('e_invoice_type');

        if($e_invoice_standard != 'PEPPOL')
            return;

        if(Ninja::isSelfHost() && ($model instanceof Invoice) && $model->company->legal_entity_id)
        {
            //self hosted sender
        }

        if(Ninja::isHosted() && ($model instanceof Invoice) && $model->company->legal_entity_id)
        {
            //hosted sender
        }

        return;
    }

    public function failed($exception = null)
    {
        if ($exception) {
            nlog("EXCEPTION:: SENDEDOCUMENT::");
            nlog($exception->getMessage());
        }

        config(['queue.failed.driver' => null]);
    }

    public function middleware()
    {
        return [new WithoutOverlapping($this->entity.$this->id.$this->db)];
    }
}
