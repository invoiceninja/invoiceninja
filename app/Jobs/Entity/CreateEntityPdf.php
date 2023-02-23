<?php

/**
 * Invoice Ninja (https://entityninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2022. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Jobs\Entity;

use App\Exceptions\FilePermissionsFailure;
use App\Libraries\MultiDB;
use App\Models\CreditInvitation;
use App\Models\InvoiceInvitation;
use App\Models\QuoteInvitation;
use App\Models\RecurringInvoiceInvitation;
use App\Services\Pdf\PdfService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;

class CreateEntityPdf implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $entity;

    private $disk;

    public $invitation;

    /**
     * Create a new job instance.
     *
     * @param $invitation
     */
    public function __construct($invitation, $disk = null)
    {

        $this->invitation = $invitation;

        $this->disk = $disk ?? config('filesystems.default');

    }

    public function handle()
    {

        $starttime = microtime(true);

        MultiDB::setDb($this->invitation->company->db);

        if ($this->invitation instanceof InvoiceInvitation) {
            $this->entity = $this->invitation->invoice;
            $path = $this->invitation->contact->client->invoice_filepath($this->invitation);
        } elseif ($this->invitation instanceof QuoteInvitation) {
            $this->entity = $this->invitation->quote;
            $path = $this->invitation->contact->client->quote_filepath($this->invitation);
        } elseif ($this->invitation instanceof CreditInvitation) {
            $this->entity = $this->invitation->credit;
            $path = $this->invitation->contact->client->credit_filepath($this->invitation);
        } elseif ($this->invitation instanceof RecurringInvoiceInvitation) {
            $this->entity = $this->invitation->recurring_invoice;
            $path = $this->invitation->contact->client->recurring_invoice_filepath($this->invitation);

        }

        $file_path = $path.$this->entity->numberFormatter().'.pdf';
        
        $pdf = (new PdfService($this->invitation))->getPdf();
        
        $endtime = microtime(true);
        nlog($endtime - $starttime);

        if ($pdf) {
            try {
                Storage::disk($this->disk)->put($file_path, $pdf);
            } catch (\Exception $e) {
                throw new FilePermissionsFailure($e->getMessage());
            }
        }
        
        
        return $file_path;
    }

    public function failed($e)
    {
    }
}
