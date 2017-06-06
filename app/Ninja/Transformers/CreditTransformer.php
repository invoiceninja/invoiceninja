<?php

namespace App\Ninja\Transformers;

use App\Models\Credit;

/**
 * Class CreditTransformer.
 */
class CreditTransformer extends EntityTransformer
{
    /**
     * @param Credit $credit
     *
     * @return array
     */
    public function transform(Credit $credit)
    {
        return array_merge($this->getDefaults($credit), [
            'id' => (int) $credit->public_id,
            'amount' => (float) $credit->amount,
            'balance' => (float) $credit->balance,
            'updated_at' => $this->getTimestamp($credit->updated_at),
            'archived_at' => $this->getTimestamp($credit->deleted_at),
            'is_deleted' => (bool) $credit->is_deleted,
            'credit_date' => $credit->credit_date,
            'credit_number' => $credit->credit_number,
            'private_notes' => $credit->private_notes,
            'public_notes' => $credit->public_notes,
            'client_id' => $credit->client->public_id,
        ]);
    }
}
