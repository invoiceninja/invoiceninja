<?php

namespace App\Listeners;

use App\Models\Payment;
use App\Models\JournalEntry;
use App\Models\ChartOfAccount;
use Carbon\Carbon;

class PostJournalEntriesForPayment
{
    public function handle(Payment $payment)
    {
        if (JournalEntry::where('source_type', Payment::class)
            ->where('source_id', $payment->id)
            ->exists()
        ) {
            return;
        }

        $companyId = $payment->company_id;
        $amount    = $payment->amount;
        $date      = Carbon::parse($payment->date ?? now());

        // Find required accounts
        $cash = ChartOfAccount::where('company_id', $companyId)
            ->where('code', '1000') // Cash
            ->first();

        $ar = ChartOfAccount::where('company_id', $companyId)
            ->where('code', '1100') // Accounts Receivable
            ->first();

        // Safety: only post if both accounts exist
        if (! $cash || ! $ar) {
            return;
        }

        // Debit Cash
        JournalEntry::create([
            'company_id'            => $companyId,
            'chart_of_account_id'   => $cash->id,
            'source_type'           => Payment::class,
            'source_id'             => $payment->id,
            'debit'                 => $amount,
            'credit'                => 0,
            'description'           => 'Payment received',
            'entry_date'            => $date,
        ]);

        // Credit Sales
        JournalEntry::create([
            'company_id'            => $companyId,
            'chart_of_account_id'   => $ar->id,
            'source_type'           => Payment::class,
            'source_id'             => $payment->id,
            'debit'                 => 0,
            'credit'                => $amount,
            'description'           => 'Payment received',
            'entry_date'            => $date,
        ]);
    }
}
