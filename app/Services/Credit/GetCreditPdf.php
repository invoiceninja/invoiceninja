<?php

namespace App\Services\Credit;

use App\Jobs\Invoice\CreateInvoicePdf;
use Illuminate\Support\Facades\Storage;

class GetCreditPdf
{

    public function __construct()
    {
    }

    public function __invoke($credit, $contact = null)
    {
        if (!$contact) {
            $contact = $credit->customer->primary_contact()->first();
        }

        $path = 'public/' . $credit->customer->id . '/credits/';
        $file_path = $path . $credit->number . '.pdf';
        $disk = config('filesystems.default');
        $file = Storage::disk($disk)->exists($file_path);

        if (!$file) {
            $file_path = CreateInvoicePdf::dispatchNow($this, $credit->account, $contact);
        }

        return Storage::disk($disk)->url($file_path);
    }

}
