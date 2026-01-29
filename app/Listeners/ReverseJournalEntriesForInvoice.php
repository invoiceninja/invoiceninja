<?php

namespace App\Listeners;

use App\Models\Invoice;
use App\Models\JournalEntry;
use Carbon\Carbon;

class ReverseJournalEntriesForInvoice
{
    public function handle(Invoice $invoice)
    {
        if (JournalEntry::where('source_type', Invoice::class)
            ->where('source_id', $invoice->id)
            ->exists()
        ) {
            return;
        }

        $entries = JournalEntry::where('source_type', Invoice::class)
            ->where('source_id', $invoice->id)
            ->get();

        if ($entries->isEmpty()) {
            return;
        }

        foreach ($entries as $entry) {
            JournalEntry::create([
                'company_id'          => $entry->company_id,
                'chart_of_account_id' => $entry->chart_of_account_id,
                'source_type'         => Invoice::class,
                'source_id'           => $invoice->id,
                'debit'               => $entry->credit,
                'credit'              => $entry->debit,
                'description'         => 'Reversal of invoice entry',
                'entry_date'          => Carbon::now(),
            ]);
        }
    }
}
