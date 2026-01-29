<?php

namespace App\Listeners;

use App\Models\Invoice;
use App\Models\JournalEntry;
use App\Models\ChartOfAccount;
use Carbon\Carbon;

class PostJournalEntriesForInvoice
{
    public function handle(Invoice $invoice)
    {
        if ($invoice->status_id != Invoice::STATUS_SENT) {
            return;
        }

        $companyId = $invoice->company_id;
        $amount    = $invoice->amount;
        $date      = Carbon::parse($invoice->date ?? now());

        $ar = ChartOfAccount::where('company_id', $companyId)
            ->where('code', '1100') // Accounts Receivable
            ->first();

        $sales = ChartOfAccount::where('company_id', $companyId)
            ->where('code', '4000') // Sales
            ->first();

        if (! $ar || ! $sales) {
            return;
        }

        JournalEntry::create([
            'company_id'          => $companyId,
            'chart_of_account_id' => $ar->id,
            'source_type'         => Invoice::class,
            'source_id'           => $invoice->id,
            'debit'               => $amount,
            'credit'              => 0,
            'description'         => 'Invoice issued',
            'entry_date'          => $date,
        ]);

        JournalEntry::create([
            'company_id'          => $companyId,
            'chart_of_account_id' => $sales->id,
            'source_type'         => Invoice::class,
            'source_id'           => $invoice->id,
            'debit'               => 0,
            'credit'              => $amount,
            'description'         => 'Invoice issued',
            'entry_date'          => $date,
        ]);
    }
}
