<?php

namespace App\Services\Credit;

use App\Jobs\Invoice\CreateInvoicePdf;
use App\Services\AbstractService;
use Illuminate\Support\Facades\Storage;

class GetCreditPdf extends AbstractService
{

    public function __construct()
    {
    }

    public function __invoke($credit, $contact = null)
    {
        if (!$contact) {
            $contact = $credit->client->primary_contact()->first();
        }

        $path = 'public/' . $credit->client->id . '/credits/';
        $file_path = $path . $credit->number . '.pdf';
        $disk = config('filesystems.default');
        $file = Storage::disk($disk)->exists($file_path);

        if (!$file) {
            $file_path = CreateInvoicePdf::dispatchNow($this, $credit->company, $contact);
        }

        return Storage::disk($disk)->url($file_path);
    }

}
