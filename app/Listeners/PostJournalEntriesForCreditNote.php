<?php

namespace App\Listeners;

use App\Models\Invoice;
use App\Models\JournalEntry;
use App\Models\ChartOfAccount;
use Carbon\Carbon;

class PostJournalEntriesForCreditNote
{
    public function handle(Invoice $invoice)
    {
        // Only act on credit notes
        if (! property_exists($invoice, 'is_credit') || ! $invoice->is_credit) {
            return;
        }

        // Prevent duplicate postings
        if (JournalEntry::where('source_type', Invoice::class)
            ->where('source_id', $invoice->id)
            ->where('description', 'LIKE', '%Credit note%')
            ->exists()) {
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

        // Credit AR (reduce receivable)
        JournalEntry::create([
            'company_id'          => $companyId,
            'chart_of_account_id' => $ar->id,
            'source_type'         => Invoice::class,
            'source_id'           => $invoice->id,
            'debit'               => 0,
            'credit'              => $amount,
            'description'         => 'Credit note issued',
            'entry_date'          => $date,
        ]);

        // Debit Sales (reverse revenue)
        JournalEntry::create([
            'company_id'          => $companyId,
            'chart_of_account_id' => $sales->id,
            'source_type'         => Invoice::class,
            'source_id'           => $invoice->id,
            'debit'               => $amount,
            'credit'              => 0,
            'description'         => 'Credit note issued',
            'entry_date'          => $date,
        ]);
    }
}
