<?php

namespace App\Ninja\Transformers;

use App\Models\Credit;

/**
 * @SWG\Definition(definition="Credit", required={"client_id"}, @SWG\Xml(name="Credit"))
 */
class CreditTransformer extends EntityTransformer
{
    /**
     * @SWG\Property(property="id", type="integer", example=1, readOnly=true)
     * @SWG\Property(property="amount", type="number", format="float", example=10, readOnly=true)
     * @SWG\Property(property="balance", type="number", format="float", example=10, readOnly=true)
     * @SWG\Property(property="updated_at", type="integer", example=1451160233, readOnly=true)
     * @SWG\Property(property="archived_at", type="integer", example=1451160233, readOnly=true)
     * @SWG\Property(property="is_deleted", type="boolean", example=false, readOnly=true)
     * @SWG\Property(property="credit_date", type="string", format="date", example="2018-01-01")
     * @SWG\Property(property="credit_number", type="string", example="Credit Number")
     * @SWG\Property(property="private_notes", type="string", example="Notes")
     * @SWG\Property(property="public_notes", type="string", example="Notes")
     * @SWG\Property(property="client_id", type="integer", example=1)
     */

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
            'credit_date' => $credit->credit_date ?: '',
            'credit_number' => $credit->credit_number ?: '',
            'private_notes' => $credit->private_notes ?: '',
            'public_notes' => $credit->public_notes ?: '',
            'client_id' => $credit->client->public_id,
        ]);
    }
}
