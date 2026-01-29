<?php

namespace App\Listeners;

use App\Models\Payment;
use App\Models\JournalEntry;
use Carbon\Carbon;

class ReverseJournalEntriesForPayment
{
    public function handle(Payment $payment)
    {
        $entries = JournalEntry::where('source_type', Payment::class)
            ->where('source_id', $payment->id)
            ->get();

        if ($entries->isEmpty()) {
            return;
        }

        foreach ($entries as $entry) {
            JournalEntry::create([
                'company_id'          => $entry->company_id,
                'chart_of_account_id' => $entry->chart_of_account_id,
                'source_type'         => Payment::class,
                'source_id'           => $payment->id,
                'debit'               => $entry->credit, // reverse
                'credit'              => $entry->debit,  // reverse
                'description'         => 'Reversal of payment entry',
                'entry_date'          => Carbon::now(),
            ]);
        }
    }
}
