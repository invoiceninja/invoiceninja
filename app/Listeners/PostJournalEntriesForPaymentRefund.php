<?php

namespace App\Listeners;

use App\Models\Payment;
use App\Models\JournalEntry;
use App\Models\ChartOfAccount;
use Carbon\Carbon;

class PostJournalEntriesForPaymentRefund
{
    public function handle(Payment $payment)
    {
        $refundAmount = (float) ($payment->refunded ?? 0);

        if ($refundAmount <= 0) {
            return;
        }

        // Prevent duplicate refund postings
        if (JournalEntry::where('source_type', Payment::class)
            ->where('source_id', $payment->id)
            ->where('description', 'LIKE', '%Refund%')
            ->exists()) {
            return;
        }

        $companyId = $payment->company_id;
        $date      = Carbon::now();

        $cash = ChartOfAccount::where('company_id', $companyId)
            ->where('code', '1000') // Cash
            ->first();

        $ar = ChartOfAccount::where('company_id', $companyId)
            ->where('code', '1100') // Accounts Receivable
            ->first();

        if (! $cash || ! $ar) {
            return;
        }

        // Debit AR (customer owes again)
        JournalEntry::create([
            'company_id'          => $companyId,
            'chart_of_account_id' => $ar->id,
            'source_type'         => Payment::class,
            'source_id'           => $payment->id,
            'debit'               => $refundAmount,
            'credit'              => 0,
            'description'         => 'Refund issued',
            'entry_date'          => $date,
        ]);

        // Credit Cash
        JournalEntry::create([
            'company_id'          => $companyId,
            'chart_of_account_id' => $cash->id,
            'source_type'         => Payment::class,
            'source_id'           => $payment->id,
            'debit'               => 0,
            'credit'              => $refundAmount,
            'description'         => 'Refund issued',
            'entry_date'          => $date,
        ]);
    }
}
