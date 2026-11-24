<?php

/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2026. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Repositories;

use App\Models\Quote;
use App\Models\QuoteInvitation;
use App\Services\Quote\QuoteOptions;
use Illuminate\Support\Facades\DB;

/**
 * QuoteRepository.
 */
class QuoteRepository extends BaseRepository
{
    public function save($data, Quote $quote): ?Quote
    {
        if ($quote->exists && ($quote->hasOptions() || $quote->hasOptionSelection())) {
            return DB::transaction(function () use ($data, $quote) {
                $quote = Quote::withTrashed()->where('company_id', $quote->company_id)->lockForUpdate()->findOrFail($quote->id);
                $service = app(QuoteOptions::class);
                $service->assertEditable($quote, $data);
                $quote = $this->alternativeSave($data, $quote);
                $service->assertConfirmed($quote->fresh());

                return $quote;
            });
        }

        return $this->alternativeSave($data, $quote);
    }

    public function getInvitationByKey($key): ?QuoteInvitation
    {
        return QuoteInvitation::query()->where('key', $key)->first();
    }
}
